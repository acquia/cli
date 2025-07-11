<?php

declare(strict_types=1);

namespace Acquia\Cli\EventListener;

use Acquia\Cli\Exception\AcquiaCliException;
use AcquiaCloudApi\Exception\ApiErrorException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Exception\RuntimeException;

/**
 * Make exceptions warm and cuddly.
 *
 * Vendor libraries and APIs throw exceptions that aren't very helpful on their
 * own. We can rewrite them to be more helpful and add support links.
 */
class ExceptionListener
{
    private string $messagesBgColor = 'blue';

    private string $messagesFgColor = 'white';

    /**
     * @var string[]
     */
    private array $helpMessages = [];

    public function onConsoleError(ConsoleErrorEvent $event): void
    {
        $exitCode = $event->getExitCode();
        $error = $event->getError();
        $errorMessage = $error->getMessage();

        if ($error instanceof IdentityProviderException && $error->getMessage() === 'invalid_client') {
            $newErrorMessage = 'Your Cloud Platform API credentials are invalid.';
            $this->helpMessages[] = "Run <bg=$this->messagesBgColor;fg=$this->messagesFgColor;options=bold>acli auth:login</> to reset your API credentials.";
        }

        if ($error instanceof RuntimeException) {
            switch ($errorMessage) {
                case 'Not enough arguments (missing: "environmentId").':
                case 'Not enough arguments (missing: "environmentUuid").':
                    $this->writeSiteAliasHelp();
                    break;
            }
        }

        if ($error instanceof AcquiaCliException) {
            switch ($error->getRawMessage()) {
                case 'No applications match the alias {applicationAlias}':
                case 'Multiple applications match the alias {applicationAlias}':
                    $this->writeApplicationAliasHelp();
                    break;
                case '{environmentId} must be a valid UUID or site alias.':
                case '{environmentUuid} must be a valid UUID or site alias.':
                    $this->writeSiteAliasHelp();
                    break;
                case 'Access token file not found at {file}':
                case 'Access token expiry file not found at {file}':
                    $this->helpMessages[] = 'Get help for this error at https://docs.acquia.com/ide/known-issues/#the-automated-cloud-platform-api-authentication-might-fail';
                    break;
                case 'This machine is not yet authenticated with the Cloud Platform.':
                    $this->helpMessages[] = 'Run `acli auth:login` to re-authenticated with the Cloud Platform.';
                    break;
                case 'This machine is not yet authenticated with Site Factory.':
                    $this->helpMessages[] = 'Run `acli auth:acsf-login` to re-authenticate with Site Factory.';
                    break;
                case 'Could not extract aliases to {destination}':
                    $this->helpMessages[] = 'Check that you have write access to the directory';
                    break;
                case 'Unable to import local database. {message}':
                    $this->helpMessages[] = 'Check for MySQL warnings above or in the server log (/var/log/mysql/error.log)';
                    $this->helpMessages[] = 'Frequently, `MySQL server has gone away` messages are caused by max_allowed_packet being exceeded.';
                    break;
                case 'Database connection details missing':
                    $this->helpMessages[] = 'Check that you have the \'View database connection details\' permission';
                    break;
                case 'No environments found for this application':
                    $this->helpMessages[] = 'Check that the application has finished provisioning';
                    break;
            }
        }

        if ($error instanceof ApiErrorException) {
            if (($command = $event->getCommand()) && $error->getResponseBody()->error === 'not_found' && $command->getName() === 'api:environments:log-download') {
                $this->helpMessages[] = "You must create logs (api:environments:log-create) prior to downloading them";
            }
            switch ($errorMessage) {
                case "There are no available Cloud IDEs for this application.\n":
                    $this->helpMessages[] = "Delete an existing IDE via <bg=$this->messagesBgColor;fg=$this->messagesFgColor;options=bold>acli ide:delete</> or contact your Account Manager or Acquia Sales to purchase additional IDEs.";
                    break;
                case "This resource requires additional authentication.":
                    $this->helpMessages[] = "This is likely because you have Federated Authentication required for your organization.";
                    $this->helpMessages[] = "Run `acli login` to authenticate via API token and then try again.";
                    break;
                default:
                    $newErrorMessage = 'Cloud Platform API returned an error: ' . $errorMessage;
                    $this->helpMessages[] = "You can learn more about Cloud Platform API at https://docs.acquia.com/cloud-platform/develop/api/";
            }
        }

        if (!empty($this->helpMessages)) {
            $this->helpMessages[0] = '<options=bold>How to fix it:</> ' . $this->helpMessages[0];
        }
        $this->helpMessages[] = "You can find Acquia CLI documentation at https://docs.acquia.com/acquia-cli/";
        $this->writeUpdateHelp($event);
        $this->writeSupportTicketHelp($event);

        if ($command = $event->getCommand()) {
            /** @var \Acquia\Cli\Application $application */
            $application = $command->getApplication();
            $application->setHelpMessages($this->helpMessages);
        }

        if (isset($newErrorMessage)) {
            $event->setError(new AcquiaCliException($newErrorMessage, [], $exitCode));
        }
    }

    private function writeApplicationAliasHelp(): void
    {
        $this->helpMessages[] = "The <bg=$this->messagesBgColor;options=bold>applicationUuid</> argument must be a valid UUID or unique application alias accessible to your Cloud Platform user." . PHP_EOL . PHP_EOL
            . "An alias consists of an application name optionally prefixed with a hosting realm, e.g. <bg=$this->messagesBgColor;fg=$this->messagesFgColor;options=bold>myapp</> or <bg=$this->messagesBgColor;fg=$this->messagesFgColor;options=bold>prod.myapp</>." . PHP_EOL . PHP_EOL
            . "Run <bg=$this->messagesBgColor;options=bold>acli remote:aliases:list</> to see a list of all available aliases.";
    }

    private function writeSiteAliasHelp(): void
    {
        $this->helpMessages[] = "<bg=$this->messagesBgColor;options=bold>environmentId</> can also be a site alias. E.g. <bg=$this->messagesBgColor;fg=$this->messagesFgColor;options=bold>myapp.dev</>." . PHP_EOL
            . "Run <bg=$this->messagesBgColor;options=bold>acli remote:aliases:list</> to see a list of all available aliases.";
    }

    private function writeSupportTicketHelp(ConsoleErrorEvent $event): void
    {
        $message = "You can submit a support ticket at https://support-acquia.force.com/s/contactsupport";
        if (!$event->getOutput()->isVeryVerbose()) {
            $message .= PHP_EOL . "Re-run the command with the <bg=$this->messagesBgColor;fg=$this->messagesFgColor;options=bold>-vvv</> flag and include the full command output in your support ticket.";
        }
        $this->helpMessages[] = $message;
    }

    private function writeUpdateHelp(ConsoleErrorEvent $event): void
    {
        try {
            $command = $event->getCommand();
            if (
                $command
                && method_exists($command, 'checkForNewVersion')
                && $latest = $command->checkForNewVersion()
            ) {
                $message = "Acquia CLI $latest is available. Try updating via <bg=$this->messagesBgColor;fg=$this->messagesFgColor;options=bold>acli self-update</> and then run the command again.";
                $this->helpMessages[] = $message;
            }
            // This command may not exist during some testing.
        } catch (CommandNotFoundException) {
        }
    }
}
