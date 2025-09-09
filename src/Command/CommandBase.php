<?php

declare(strict_types=1);

namespace Acquia\Cli\Command;

use Acquia\Cli\AcsfApi\AcsfClientService;
use Acquia\Cli\ApiCredentialsInterface;
use Acquia\Cli\Attribute\RequireAuth;
use Acquia\Cli\Attribute\RequireLocalDb;
use Acquia\Cli\Attribute\RequireRemoteDb;
use Acquia\Cli\CloudApi\ClientService;
use Acquia\Cli\Command\Ssh\SshKeyCommandBase;
use Acquia\Cli\DataStore\AcquiaCliDatastore;
use Acquia\Cli\DataStore\CloudDataStore;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Helpers\AliasCache;
use Acquia\Cli\Helpers\DataStoreContract;
use Acquia\Cli\Helpers\LocalMachineHelper;
use Acquia\Cli\Helpers\LoopHelper;
use Acquia\Cli\Helpers\SshHelper;
use Acquia\Cli\Helpers\TelemetryHelper;
use Acquia\Cli\Output\Checklist;
use Acquia\Cli\Transformer\EnvironmentTransformer;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Connector\Connector;
use AcquiaCloudApi\Endpoints\Account;
use AcquiaCloudApi\Endpoints\Applications;
use AcquiaCloudApi\Endpoints\CodebaseEnvironments;
use AcquiaCloudApi\Endpoints\Codebases;
use AcquiaCloudApi\Endpoints\Databases;
use AcquiaCloudApi\Endpoints\Environments;
use AcquiaCloudApi\Endpoints\Notifications;
use AcquiaCloudApi\Endpoints\Organizations;
use AcquiaCloudApi\Endpoints\SiteInstances;
use AcquiaCloudApi\Endpoints\Sites;
use AcquiaCloudApi\Endpoints\Subscriptions;
use AcquiaCloudApi\Response\AccountResponse;
use AcquiaCloudApi\Response\ApplicationResponse;
use AcquiaCloudApi\Response\CodebaseEnvironmentResponse;
use AcquiaCloudApi\Response\CodebaseResponse;
use AcquiaCloudApi\Response\CodebasesResponse;
use AcquiaCloudApi\Response\DatabaseResponse;
use AcquiaCloudApi\Response\DatabasesResponse;
use AcquiaCloudApi\Response\EnvironmentResponse;
use AcquiaCloudApi\Response\EnvironmentsResponse;
use AcquiaCloudApi\Response\NotificationResponse;
use AcquiaCloudApi\Response\SiteInstanceDatabaseResponse;
use AcquiaCloudApi\Response\SiteInstanceResponse;
use AcquiaCloudApi\Response\SiteResponse;
use AcquiaCloudApi\Response\SubscriptionResponse;
use AcquiaLogstream\LogstreamManager;
use ArrayObject;
use Closure;
use Exception;
use JsonException;
use loophp\phposinfo\OsInfo;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Safe\Exceptions\FilesystemException;
use SelfUpdate\SelfUpdateManager;
use stdClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Terminal;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Yaml\Yaml;
use Zumba\Amplitude\Amplitude;

abstract class CommandBase extends Command implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected InputInterface $input;

    protected OutputInterface $output;

    protected SymfonyStyle $io;

    protected FormatterHelper $formatter;

    private ApplicationResponse $cloudApplication;

    protected string $siteId = "";

    protected string $dir;

    protected string $localDbUser = 'drupal';

    protected string $localDbPassword = 'drupal';

    protected string $localDbName = 'drupal';

    protected string $localDbHost = 'localhost';

    protected bool $drushHasActiveDatabaseConnection;

    public function __construct(
        public LocalMachineHelper $localMachineHelper,
        protected CloudDataStore $datastoreCloud,
        protected AcquiaCliDatastore $datastoreAcli,
        protected ApiCredentialsInterface $cloudCredentials,
        protected TelemetryHelper $telemetryHelper,
        protected string $projectDir,
        protected ClientService $cloudApiClientService,
        public SshHelper $sshHelper,
        protected string $sshDir,
        LoggerInterface $logger,
        public selfUpdateManager $selfUpdateManager,
    ) {
        $this->logger = $logger;
        $this->setLocalDbPassword();
        $this->setLocalDbUser();
        $this->setLocalDbName();
        $this->setLocalDbHost();
        parent::__construct();
        if ((new ReflectionClass(static::class))->getAttributes(RequireAuth::class)) {
            $this->appendHelp('This command requires authentication via the Cloud Platform API.');
        }
        if ((new ReflectionClass(static::class))->getAttributes(RequireLocalDb::class)) {
            $this->appendHelp('This command requires an active database connection. Set the following environment variables prior to running this command: '
                . 'ACLI_DB_HOST, ACLI_DB_NAME, ACLI_DB_USER, ACLI_DB_PASSWORD');
        }
        if ((new ReflectionClass(static::class))->getAttributes(RequireRemoteDb::class)) {
            $this->appendHelp('This command requires the \'View database connection details\' permission.');
        }
    }

    public function appendHelp(string $helpText): void
    {
        $currentHelp = $this->getHelp();
        $helpText = $currentHelp ? $currentHelp . "\n" . $helpText : $currentHelp . $helpText;
        $this->setHelp($helpText);
    }

    protected static function getUuidRegexConstraint(): Regex
    {
        return new Regex([
            'message' => 'This is not a valid UUID.',
            'pattern' => '/^[0-9A-F]{8}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{12}$/i',
        ]);
    }

    public function setProjectDir(string $projectDir): void
    {
        $this->projectDir = $projectDir;
    }

    public function getProjectDir(): string
    {
        return $this->projectDir;
    }

    private function setLocalDbUser(): void
    {
        if (getenv('ACLI_DB_USER')) {
            $this->localDbUser = getenv('ACLI_DB_USER');
        }
    }

    public function getLocalDbUser(): string
    {
        return $this->localDbUser;
    }

    private function setLocalDbPassword(): void
    {
        if (getenv('ACLI_DB_PASSWORD')) {
            $this->localDbPassword = getenv('ACLI_DB_PASSWORD');
        }
    }

    public function getLocalDbPassword(): string
    {
        return $this->localDbPassword;
    }

    private function setLocalDbName(): void
    {
        if (getenv('ACLI_DB_NAME')) {
            $this->localDbName = getenv('ACLI_DB_NAME');
        }
    }

    public function getLocalDbName(): string
    {
        return $this->localDbName;
    }

    private function setLocalDbHost(): void
    {
        if (getenv('ACLI_DB_HOST')) {
            $this->localDbHost = getenv('ACLI_DB_HOST');
        }
    }

    public function getLocalDbHost(): string
    {
        return $this->localDbHost;
    }

    /**
     * Initializes the command just after the input has been validated.
     *
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->input = $input;
        $this->output = $output;
        $this->io = new SymfonyStyle($input, $output);
        // Register custom progress bar format.
        ProgressBar::setFormatDefinition(
            'message',
            "%current%/%max% [%bar%] <info>%percent:3s%%</info> -- %elapsed:6s%/%estimated:-6s%\n %message%"
        );
        $this->formatter = $this->getHelper('formatter');

        $this->output->writeln('Acquia CLI version: ' . $this->getApplication()
            ->getVersion(), OutputInterface::VERBOSITY_DEBUG);
        if (getenv('ACLI_NO_TELEMETRY') !== 'true') {
            $this->checkAndPromptTelemetryPreference();
            $this->telemetryHelper->initialize();
        }
        $this->checkAuthentication();

        $this->fillMissingRequiredApplicationUuid($input, $output);
        $this->convertApplicationAliasToUuid($input);
        $this->convertUserAliasToUuid($input, 'userUuid', 'organizationUuid');
        $this->convertEnvironmentAliasToUuid($input, 'environmentId');
        $this->convertEnvironmentAliasToUuid($input, 'source-environment');
        $this->convertEnvironmentAliasToUuid($input, 'destination-environment');
        $this->convertEnvironmentAliasToUuid($input, 'source');
        $this->convertNotificationToUuid($input, 'notificationUuid');
        $this->convertNotificationToUuid($input, 'notification-uuid');

        if ($latest = $this->checkForNewVersion()) {
            $this->output->writeln("Acquia CLI $latest is available. Run <options=bold>acli self-update</> to update.");
        }
    }

    /**
     * Check if telemetry preference is set, prompt if not.
     */
    public function checkAndPromptTelemetryPreference(): void
    {
        $sendTelemetry = $this->datastoreCloud->get(DataStoreContract::SEND_TELEMETRY);
        if (!isset($sendTelemetry) && $this->getName() !== 'telemetry' && $this->input->isInteractive()) {
            $this->output->writeln('We strive to give you the best tools for development.');
            $this->output->writeln('You can really help us improve by sharing anonymous performance and usage data.');
            $style = new SymfonyStyle($this->input, $this->output);
            $pref = $style->confirm('Would you like to share anonymous performance usage and data?');
            $this->datastoreCloud->set(DataStoreContract::SEND_TELEMETRY, $pref);
            if ($pref) {
                $this->output->writeln('Awesome! Thank you for helping!');
            } else {
                // @todo Completely anonymously send an event to indicate some user opted out.
                $this->output->writeln('Ok, no data will be collected and shared with us.');
                $this->output->writeln('We take privacy seriously.');
                $this->output->writeln('If you change your mind, run <options=bold>acli telemetry</>.');
            }
        }
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        $exitCode = parent::run($input, $output);
        if (
            $exitCode === 0 && in_array($input->getFirstArgument(), [
                'self-update',
                'update',
            ])
        ) {
            // Exit immediately to avoid loading additional classes breaking updates.
            // @see https://github.com/acquia/cli/issues/218
            return $exitCode;
        }
        $eventProperties = [
            'app_version' => $this->getApplication()->getVersion(),
            'arguments' => $input->getArguments(),
            'exit_code' => $exitCode,
            'options' => $input->getOptions(),
            'os_name' => OsInfo::os(),
            'os_version' => OsInfo::version(),
            'platform' => OsInfo::family(),
        ];
        Amplitude::getInstance()->queueEvent('Ran command', $eventProperties);

        return $exitCode;
    }

    /**
     * Add argument and usage examples for applicationUuid.
     */
    protected function acceptApplicationUuid(): static
    {
        $this->addArgument('applicationUuid', InputArgument::OPTIONAL, 'The Cloud Platform application UUID or alias (i.e. an application name optionally prefixed with the realm)')
            ->addUsage('[<applicationAlias>]')
            ->addUsage('myapp')
            ->addUsage('prod:myapp')
            ->addUsage('abcd1234-1111-2222-3333-0e02b2c3d470');

        return $this;
    }

    /**
     * Add argument and usage examples for codebaseId.
     */
    protected function acceptCodebaseId(): static
    {
        $this->addArgument('codebaseId', InputArgument::OPTIONAL, 'The Cloud Platform codebase ID')
            ->addUsage('abcd1234-1111-2222-3333-0e02b2c3d470');

        return $this;
    }
    /**
     * Add argument and usage examples for environmentId.
     */
    protected function acceptEnvironmentId(): static
    {
        $this->addArgument('environmentId', InputArgument::OPTIONAL, 'The Cloud Platform environment ID or alias (i.e. an application and environment name optionally prefixed with the realm)')
            ->addUsage('[<environmentAlias>]')
            ->addUsage('myapp.dev')
            ->addUsage('prod:myapp.dev')
            ->addUsage('12345-abcd1234-1111-2222-3333-0e02b2c3d470');

        return $this;
    }

    /**
     * Add argument and usage examples for SiteInstanceId.
     */
    protected function acceptSiteInstanceId(): static
    {
        $this->addOption('siteInstanceId', null, InputOption::VALUE_OPTIONAL, 'The Site Instance ID (SITEID.EnvironmentID)')
            ->addUsage('3e8ecbec-ea7c-4260-8414-ef2938c859bc.abcd1234-1111-2222-3333-0e02b2c3d470');

        return $this;
    }

    /**
     * Add site argument.
     *
     * Only call this after acceptEnvironmentId() to keep arguments in the
     * expected order.
     *
     * @return $this
     */
    protected function acceptSite(): self
    {
        // Do not set a default site in order to force a user prompt.
        $this->addArgument('site', InputArgument::OPTIONAL, 'For a multisite application, the directory name of the site')
            ->addUsage('myapp.dev default');

        return $this;
    }

    /**
     * Prompts the user to choose from a list of available Cloud Platform
     * applications.
     *
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    private function promptChooseSubscription(
        Client $acquiaCloudClient
    ): ?SubscriptionResponse {
        $subscriptionsResource = new Subscriptions($acquiaCloudClient);
        $customerSubscriptions = $subscriptionsResource->getAll();

        if (!$customerSubscriptions->count()) {
            throw new AcquiaCliException("You have no Cloud subscriptions.");
        }
        return $this->promptChooseFromObjectsOrArrays(
            $customerSubscriptions,
            'uuid',
            'name',
            'Select a Cloud Platform subscription:'
        );
    }

    /**
     * Prompts the user to choose from a list of available Cloud Platform
     * applications.
     *
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    private function promptChooseApplication(
        Client $acquiaCloudClient
    ): object|array|null {
        $applicationsResource = new Applications($acquiaCloudClient);
        $customerApplications = $applicationsResource->getAll();

        if (!$customerApplications->count()) {
            throw new AcquiaCliException("You have no Cloud applications.");
        }
        return $this->promptChooseFromObjectsOrArrays(
            $customerApplications,
            'uuid',
            'name',
            'Select a Cloud Platform application:'
        );
    }

    /**
     * Prompts the user to choose from a list of environments for a given Cloud
     * Platform application.
     *
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    private function promptChooseEnvironment(
        Client $acquiaCloudClient,
        string $applicationUuid
    ): object|array|null {
        $environmentResource = new Environments($acquiaCloudClient);
        $environments = $environmentResource->getAll($applicationUuid);
        if (!$environments->count()) {
            throw new AcquiaCliException('There are no environments associated with this application.');
        }
        return $this->promptChooseFromObjectsOrArrays(
            $environments,
            'uuid',
            'name',
            'Select a Cloud Platform environment:'
        );
    }

    /**
     * Prompts the user to choose from a list of logs for a given Cloud
     * Platform environment.
     */
    protected function promptChooseLogs(): object|array|null
    {
        $logs = array_map(static function (mixed $logType, mixed $logLabel): array {
            return [
                'label' => $logLabel,
                'type' => $logType,
            ];
        }, array_keys(LogstreamManager::AVAILABLE_TYPES), LogstreamManager::AVAILABLE_TYPES);
        return $this->promptChooseFromObjectsOrArrays(
            $logs,
            'type',
            'label',
            'Select one or more logs as a comma-separated list:',
            true
        );
    }

    /**
     * Prompt a user to choose from a list.
     *
     * The list is generated from an array of objects. The objects much have at
     * least one unique property and one property that can be used as a
     * human-readable label.
     *
     * @param array[]|object[] $items An array of objects or arrays.
     * @param string $uniqueProperty The property of the $item that will be
     *     used to identify the object.
     */
    protected function promptChooseFromObjectsOrArrays(array|ArrayObject $items, string $uniqueProperty, string $labelProperty, string $questionText, bool $multiselect = false): object|array|null
    {
        $list = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                $list[$item[$uniqueProperty]] = trim($item[$labelProperty]);
            } else {
                $list[$item->$uniqueProperty] = trim($item->$labelProperty);
            }
        }
        $labels = array_values($list);
        $default = $multiselect ? 0 : $labels[0];
        $question = new ChoiceQuestion($questionText, $labels, $default);
        $question->setMultiselect($multiselect);
        $choiceId = $this->io->askQuestion($question);
        if (!$multiselect) {
            $identifier = array_search($choiceId, $list, true);
            foreach ($items as $item) {
                if (is_array($item)) {
                    if ($item[$uniqueProperty] === $identifier) {
                        return $item;
                    }
                } elseif ($item->$uniqueProperty === $identifier) {
                    return $item;
                }
            }
        } else {
            $chosen = [];
            foreach ($choiceId as $choice) {
                $identifier = array_search($choice, $list, true);
                foreach ($items as $item) {
                    if (is_array($item)) {
                        if ($item[$uniqueProperty] === $identifier) {
                            $chosen[] = $item;
                        }
                    } elseif ($item->$uniqueProperty === $identifier) {
                        $chosen[] = $item;
                    }
                }
            }
            return $chosen;
        }

        return null;
    }

    protected function getHostFromDatabaseResponse(mixed $environment, DatabaseResponse $database): string
    {
        if ($this->isAcsfEnv($environment)) {
            return $database->db_host . '.enterprise-g1.hosting.acquia.com';
        }

        return $database->db_host;
    }

    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    protected function rsyncFiles(string $sourceDir, string $destinationDir, ?callable $outputCallback): void
    {
        $this->localMachineHelper->checkRequiredBinariesExist(['rsync']);
        $command = [
            'rsync',
            // -a archive mode; same as -rlptgoD.
            // -z compress file data during the transfer.
            // -v increase verbosity.
            // -P show progress during transfer.
            // -h output numbers in a human-readable format.
            // -e specify the remote shell to use.
            '-avPhze',
            'ssh -o StrictHostKeyChecking=no',
            $sourceDir . '/',
            $destinationDir,
        ];
        $process = $this->localMachineHelper->execute($command, $outputCallback, null, ($this->output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL));
        if (!$process->isSuccessful()) {
            throw new AcquiaCliException('Unable to sync files. {message}', ['message' => $process->getErrorOutput()]);
        }
    }

    protected function getCloudFilesDir(EnvironmentResponse $chosenEnvironment, string $site): string
    {
        $sitegroup = self::getSitegroup($chosenEnvironment);
        $envAlias = self::getEnvironmentAlias($chosenEnvironment);
        if ($this->isAcsfEnv($chosenEnvironment)) {
            return "/mnt/files/$envAlias/sites/g/files/$site/files";
        }
        return $this->getCloudSitesPath($chosenEnvironment, $sitegroup) . "/$site/files";
    }

    protected function getLocalFilesDir(string $site): string
    {
        return $this->dir . '/docroot/sites/' . $site . '/files';
    }

    /**
     * @return DatabaseResponse[]
     */
    protected function determineCloudDatabases(Client $acquiaCloudClient, EnvironmentResponse $chosenEnvironment, ?string $site = null, bool $multipleDbs = false): array
    {
        $codebaseUuid = self::getCodebaseUuid();
        if ($codebaseUuid) {
            $database = EnvironmentTransformer::transformSiteInstanceDatabase($this->getSiteInstanceDatabase($this->siteId, $chosenEnvironment->uuid));
            if ($database) {
                return [$database];
            }
        }
        $databasesRequest = new Databases($acquiaCloudClient);
        $databases = $databasesRequest->getAll($chosenEnvironment->uuid);

        if (count($databases) === 1) {
            $this->logger->debug('Only a single database detected on Cloud');
            return [$databases[0]];
        }
        $this->logger->debug('Multiple databases detected on Cloud');
        if ($site && !$multipleDbs) {
            if ($site === 'default') {
                $this->logger->debug('Site is set to default. Assuming default database');
                $site = self::getSitegroup($chosenEnvironment);
            }
            $databaseNames = array_column((array) $databases, 'name');
            $databaseKey = array_search($site, $databaseNames, true);
            if ($databaseKey !== false) {
                return [$databases[$databaseKey]];
            }
        }
        return $this->promptChooseDatabases($chosenEnvironment, $databases, $multipleDbs);
    }

    /**
     * @return array<mixed>
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     * @throws \JsonException
     */
    private function promptChooseDatabases(
        EnvironmentResponse $cloudEnvironment,
        DatabasesResponse $environmentDatabases,
        bool $multipleDbs
    ): array {
        $choices = [];
        if ($multipleDbs) {
            $choices['all'] = 'All';
        }
        $defaultDatabaseIndex = 0;
        if ($this->isAcsfEnv($cloudEnvironment)) {
            $acsfSites = $this->getAcsfSites($cloudEnvironment);
        }
        foreach ($environmentDatabases as $index => $database) {
            $suffix = '';
            if (isset($acsfSites)) {
                foreach ($acsfSites['sites'] as $domain => $acsfSite) {
                    if ($acsfSite['conf']['gardens_db_name'] === $database->name) {
                        $suffix .= ' (' . $domain . ')';
                        break;
                    }
                }
            }
            if ($database->flags->default) {
                $defaultDatabaseIndex = $index;
                $suffix .= ' (default)';
            }
            $choices[] = $database->name . $suffix;
        }

        $question = new ChoiceQuestion(
            $multipleDbs ? 'Choose databases. You may choose multiple. Use commas to separate choices.' : 'Choose a database.',
            $choices,
            $defaultDatabaseIndex
        );
        $question->setMultiselect($multipleDbs);
        if ($multipleDbs) {
            $chosenDatabaseKeys = $this->io->askQuestion($question);
            $chosenDatabases = [];
            if (count($chosenDatabaseKeys) === 1 && $chosenDatabaseKeys[0] === 'all') {
                if (count($environmentDatabases) > 10) {
                    $this->io->warning('You have chosen to pull down more than 10 databases. This could exhaust your disk space.');
                }
                return (array) $environmentDatabases;
            }
            foreach ($chosenDatabaseKeys as $chosenDatabaseKey) {
                $chosenDatabases[] = $environmentDatabases[$chosenDatabaseKey];
            }

            return $chosenDatabases;
        }

        $chosenDatabaseLabel = $this->io->choice('Choose a database', $choices, $defaultDatabaseIndex);
        $chosenDatabaseIndex = array_search($chosenDatabaseLabel, $choices, true);
        return [$environmentDatabases[$chosenDatabaseIndex]];
    }

    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    protected function determineEnvironment(InputInterface $input, OutputInterface $output, bool $allowProduction = false, bool $allowNode = false): array|string|EnvironmentResponse
    {
        if ($input->hasOption('siteInstanceId') && $input->getOption('siteInstanceId')) {
            $siteInstance = $this->determineSiteInstance($input);
            $chosenEnvironment = EnvironmentTransformer::transform($siteInstance->environment);
            $chosenEnvironment->vcs->url = $siteInstance->environment->codebase->vcs_url ?? '';
        } elseif ($input->getArgument('environmentId')) {
            $environmentId = $input->getArgument('environmentId');
            $chosenEnvironment = $this->getCloudEnvironment($environmentId);
        } else {
            $chosenEnvironment = $this->determineCodebaseEnvironment($input, $output);
            if (!$chosenEnvironment) {
                $cloudApplicationUuid = $this->determineCloudApplication();
                $cloudApplication = $this->getCloudApplication($cloudApplicationUuid);
                $output->writeln(sprintf('Using Cloud Application <options=bold>%s</>', $cloudApplication->name));
                $acquiaCloudClient = $this->cloudApiClientService->getClient();
                $chosenEnvironment = $this->promptChooseEnvironmentConsiderProd($acquiaCloudClient, $cloudApplicationUuid, $allowProduction, $allowNode);
            }
        }
        $this->logger->debug("Using environment $chosenEnvironment->label $chosenEnvironment->uuid");

        return $chosenEnvironment;
    }

    /**
     * Determine environment using AH_CODEBASE_UUID.
     *
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    private function determineCodebaseEnvironment(InputInterface $input, OutputInterface $output): ?EnvironmentResponse
    {

        // Check for AH_CODEBASE_UUID.
        $codebaseUuid = self::getCodebaseUuid();

        if (!$codebaseUuid) {
            return null;
        }
        $output->writeln(sprintf(
            'Detected Codebase UUID: <options=bold>%s</>',
            $codebaseUuid
        ));

        // Get codebase information.
        $codebase = $this->getCodebase($codebaseUuid);
        $output->writeln(sprintf(
            'Using codebase: <options=bold>%s</>',
            $codebase->label
        ));

        // Get environments for this codebase.
        $environments = $this->getEnvironmentsByCodebase($codebase);

        if (empty($environments)) {
            throw new AcquiaCliException('No environments found for this codebase.');
        }

        // Prompt user to choose environment.
        $chosenEnvironment = $this->promptChooseCodebaseEnvironment($environments);

        return $chosenEnvironment;
    }


    /**
     * Get environments by codebase UUID.
     *
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    /**
     * @return array<mixed>
     */
    private function getEnvironmentsByCodebase(CodebaseResponse $codebase): array
    {
        try {
            // Use the codebase environments endpoint.
            $codebaseEnvironmentResource = new CodebaseEnvironments($this->cloudApiClientService->getClient());
            $codebaseEnvironments = $codebaseEnvironmentResource->getAll($codebase->id);
            // Transform codebase environments to standard environment format.
            $environments = [];
            foreach ($codebaseEnvironments as $codebaseEnv) {
                $codebaseEnv->codebase = $codebase;
                $environments[] = EnvironmentTransformer::transform($codebaseEnv);
            }

            return $environments;
        } catch (Exception $e) {
            throw new AcquiaCliException('Failed to fetch environments for codebase: ' . $e->getMessage());
        }
    }

    /**
     * Prompt user to choose from codebase environments.
     *
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    private function promptChooseCodebaseEnvironment(array $environments): EnvironmentResponse
    {
        if (count($environments) === 1) {
            return reset($environments);
        }

        $choices = [];
        foreach ($environments as $environment) {
            $choices[] = "$environment->label, $environment->name (branch: {$environment->vcs->branch})";
        }

        $chosenEnvironmentLabel = $this->io->choice('Choose a Cloud Platform environment', $choices, $choices[0]);
        $chosenEnvironmentIndex = array_search($chosenEnvironmentLabel, $choices, true);
        return $environments[$chosenEnvironmentIndex];
    }

    /**
     * Get sites by codebase UUID.
     *
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    /**
     * @return array<mixed>
     */
    private function getSitesByCodebase(string $codebaseUuid): array
    {
        $acquiaCloudClient = $this->cloudApiClientService->getClient();
        $response = $acquiaCloudClient->request('get', "/codebases/$codebaseUuid/sites");

        if (!isset($response->_embedded->items)) {
            return (array) $response;
        }

        return (array) $response->_embedded->items;
    }

    /**
     * Determine site instance from IDE context.
     *
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    protected function determineSiteInstanceFromCodebaseUuid(EnvironmentResponse $environment, InputInterface $input, OutputInterface $output): ?string
    {
        // Check if we're in IDE context and have a codebase UUID.
        $codebaseUuid = self::getCodebaseUuid();
        if (!$codebaseUuid) {
            return null;
        }

        // Get sites for this codebase.
        $sites = $this->getSitesByCodebase($codebaseUuid);

        if (empty($sites)) {
            $output->writeln('<comment>No sites found for this codebase.</comment>');
            return null;
        }

        // Get site instances for the selected environment.
        $siteInstances = [];
        foreach ($sites as $site) {
            $siteInstance = $this->getSiteInstance($site->id, $environment->uuid);
            if ($siteInstance) {
                $siteInstanceObj = new stdClass();
                $siteInstanceObj->name = $site->name;
                $siteInstanceObj->siteInstanceId = $site->id . '.' . $environment->uuid;
                $siteInstances[] = $siteInstanceObj;
            }
        }
        // If only one site instance, use it automatically.
        if (count($siteInstances) === 1) {
            $selectedInstance = reset($siteInstances);
            return $selectedInstance->siteInstanceId;
        }

        if (count($siteInstances) === 0) {
            return null;
        }

        // Prompt user to choose site instance.
        $choices = array_map(function ($instance) {
            return $instance->name;
        }, $siteInstances);

        $chosenSiteLabel = $this->io->choice('Choose a site instance', $choices, $choices[0]);
        $chosenSiteIndex = array_search($chosenSiteLabel, $choices, true);

        $selectedInstance = $siteInstances[$chosenSiteIndex];
        return $selectedInstance->siteInstanceId;
    }


    /**
     * Determine the site instance for the given environment.
     *
     * This method determines an environment that contains the specified site.
     * Until the SiteInstances endpoint is available in the SDK, this method
     * combines environment and site determination.
     */
    protected function determineSiteInstance(InputInterface $input): ?SiteInstanceResponse
    {
        $siteInstanceId = $input->getOption('siteInstanceId');
        if ($siteInstanceId) {
            $siteEnvParts = explode('.', $siteInstanceId);
            if (count($siteEnvParts) !== 2) {
                throw new AcquiaCliException('Site instance ID must be in the format <siteId>.<environmentId>');
            }
            [$siteId, $environmentId] = $siteEnvParts;
            $environment = $this->getCodebaseEnvironment($environmentId);
            if (!$environment) {
                throw new AcquiaCliException("Environment with ID $environmentId not found.");
            }
            $site = $this->getSite($siteId);
            if (!$site) {
                throw new AcquiaCliException("Site with ID $siteId not found.");
            }
            $siteInstance = $this->getSiteInstance($siteId, $environmentId);
            $siteInstance->site = $site;
            $environment->codebase = $this->getCodebase($environment->codebase_uuid);
            $siteInstance->environment = $environment;
            return $siteInstance;
        }
        return null;
    }
    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    private function promptChooseEnvironmentConsiderProd(Client $acquiaCloudClient, string $applicationUuid, bool $allowProduction, bool $allowNode): EnvironmentResponse
    {
        $environmentResource = new Environments($acquiaCloudClient);
        $applicationEnvironments = iterator_to_array($environmentResource->getAll($applicationUuid));
        $choices = [];
        foreach ($applicationEnvironments as $key => $environment) {
            $productionNotAllowed = !$allowProduction && $environment->flags->production;
            $nodeNotAllowed = !$allowNode && $environment->type === 'node';
            if ($productionNotAllowed || $nodeNotAllowed) {
                unset($applicationEnvironments[$key]);
                // Re-index array so keys match those in $choices.
                $applicationEnvironments = array_values($applicationEnvironments);
                continue;
            }
            $choices[] = "$environment->label, $environment->name (vcs: {$environment->vcs->path})";
        }
        if (count($choices) === 0) {
            throw new AcquiaCliException('No compatible environments found');
        }
        $chosenEnvironmentLabel = $this->io->choice('Choose a Cloud Platform environment', $choices, $choices[0]);
        $chosenEnvironmentIndex = array_search($chosenEnvironmentLabel, $choices, true);

        return $applicationEnvironments[$chosenEnvironmentIndex];
    }

    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    protected function isLocalGitRepoDirty(): bool
    {
        $this->localMachineHelper->checkRequiredBinariesExist(['git']);
        $process = $this->localMachineHelper->executeFromCmd(
            // Problem with this is that it stages changes for the user. They may
            // not want that.
            'git add . && git diff-index --cached --quiet HEAD',
            null,
            $this->dir,
            false
        );

        return !$process->isSuccessful();
    }

    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    protected function getLocalGitCommitHash(): string
    {
        $this->localMachineHelper->checkRequiredBinariesExist(['git']);
        $process = $this->localMachineHelper->execute([
            'git',
            'rev-parse',
            'HEAD',
        ], null, $this->dir, false);

        if (!$process->isSuccessful()) {
            throw new AcquiaCliException("Unable to determine Git commit hash.");
        }

        return trim($process->getOutput());
    }

    /**
     * Load configuration from .git/config.
     *
     * @return string[][]
     *   A multidimensional array keyed by file section.
     * @throws \Safe\Exceptions\FilesystemException
     */
    private function getGitConfig(): array
    {
        $filePath = $this->projectDir . '/.git/config';
        return @\Safe\parse_ini_file($filePath, true);
    }

    /**
     * Gets an array of git remotes from a .git/config array.
     *
     * @param string[][] $gitConfig
     * @return string[]
     *   A flat array of git remote urls.
     */
    private function getGitRemotes(array $gitConfig): array
    {
        $localVcsRemotes = [];
        foreach ($gitConfig as $sectionName => $section) {
            if (
                array_key_exists('url', $section) &&
                str_contains($sectionName, 'remote ') &&
                (strpos($section['url'], 'acquia.com') || strpos($section['url'], 'acquia-sites.com'))
            ) {
                $localVcsRemotes[] = $section['url'];
            }
        }

        return $localVcsRemotes;
    }

    private function findCloudApplicationByGitUrl(
        Client $acquiaCloudClient,
        array $localGitRemotes
    ): ?ApplicationResponse {

        // Set up API resources.
        $applicationsResource = new Applications($acquiaCloudClient);
        $customerApplications = $applicationsResource->getAll();
        $environmentsResource = new Environments($acquiaCloudClient);

        // Create progress bar.
        $count = count($customerApplications);
        $progressBar = new ProgressBar($this->output, $count);
        $progressBar->setFormat('message');
        $progressBar->setMessage("Searching <options=bold>$count applications</> on the Cloud Platform...");
        $progressBar->start();

        // Search Cloud applications.
        $terminalWidth = (new Terminal())->getWidth();
        foreach ($customerApplications as $application) {
            // Ensure that the message takes up the full terminal width to prevent display artifacts.
            $message = "Searching <options=bold>$application->name</> for matching git URLs";
            $suffixLength = $terminalWidth - strlen($message) - 17;
            $suffix = $suffixLength > 0 ? str_repeat(' ', $suffixLength) : '';
            $progressBar->setMessage($message . $suffix);
            $applicationEnvironments = $environmentsResource->getAll($application->uuid);
            if (
                $application = $this->searchApplicationEnvironmentsForGitUrl(
                    $application,
                    $applicationEnvironments,
                    $localGitRemotes
                )
            ) {
                $progressBar->finish();
                $progressBar->clear();

                return $application;
            }
            $progressBar->advance();
        }
        $progressBar->finish();
        $progressBar->clear();

        return null;
    }

    protected function createTable(OutputInterface $output, string $title, array $headers, ?array $widths = null): Table
    {
        $terminalWidth = (new Terminal())->getWidth();
        $terminalWidth *= .90;
        $table = new Table($output);
        $table->setHeaders($headers);
        $table->setHeaderTitle($title);
        if ($widths !== null) {
            $setWidths = static function (float $width) use ($terminalWidth) {
                return (int) ($terminalWidth * $width);
            };
            $table->setColumnWidths(array_map($setWidths, $widths));
        }
        return $table;
    }

    private function searchApplicationEnvironmentsForGitUrl(
        ApplicationResponse $application,
        EnvironmentsResponse $applicationEnvironments,
        array $localGitRemotes
    ): ?ApplicationResponse {
        foreach ($applicationEnvironments as $environment) {
            if ($environment->flags->production && in_array($environment->vcs->url, $localGitRemotes, true)) {
                $this->logger->debug("Found matching Cloud application! $application->name with uuid $application->uuid matches local git URL {$environment->vcs->url}");

                return $application;
            }
        }

        return null;
    }

    /**
     * Infer which Cloud Platform application is associated with the current
     * local git repository.
     *
     * If the local git repository has a remote with a URL that matches a Cloud
     * Platform application's VCS URL, assume that we have a match.
     *
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    protected function inferCloudAppFromLocalGitConfig(
        Client $acquiaCloudClient
    ): ?ApplicationResponse {
        if ($this->projectDir && $this->input->isInteractive()) {
            $this->output->writeln("There is no Cloud Platform application linked to <options=bold>$this->projectDir/.git</>.");
            $answer = $this->io->confirm('Would you like Acquia CLI to search for a Cloud application that matches your local git config?');
            if ($answer) {
                $this->output->writeln('Searching for a matching Cloud application...');
                try {
                    $gitConfig = $this->getGitConfig();
                    $localGitRemotes = $this->getGitRemotes($gitConfig);
                    if (
                        $cloudApplication = $this->findCloudApplicationByGitUrl(
                            $acquiaCloudClient,
                            $localGitRemotes
                        )
                    ) {
                        $this->output->writeln('<info>Found a matching application!</info>');
                        return $cloudApplication;
                    }

                    $this->output->writeln('<comment>Could not find a matching Cloud application.</comment>');
                    return null;
                } catch (FilesystemException $e) {
                    throw new AcquiaCliException($e->getMessage());
                }
            }
        }

        return null;
    }

    /**
     * @return array<mixed>
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    protected function getSubscriptionApplications(Client $client, SubscriptionResponse $subscription): array
    {
        $applicationsResource = new Applications($client);
        $applications = $applicationsResource->getAll();
        $subscriptionApplications = [];

        foreach ($applications as $application) {
            if ($application->subscription->uuid === $subscription->uuid) {
                $subscriptionApplications[] = $application;
            }
        }
        if (count($subscriptionApplications) === 0) {
            throw new AcquiaCliException("You do not have access to any applications on the $subscription->name subscription");
        }
        return $subscriptionApplications;
    }

    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    protected function determineCloudSubscription(): SubscriptionResponse
    {
        $acquiaCloudClient = $this->cloudApiClientService->getClient();

        if ($this->input->hasArgument('subscriptionUuid') && $this->input->getArgument('subscriptionUuid')) {
            $cloudSubscriptionUuid = $this->input->getArgument('subscriptionUuid');
            self::validateUuid($cloudSubscriptionUuid);
            return (new Subscriptions($acquiaCloudClient))->get($cloudSubscriptionUuid);
        }

        // Finally, just ask.
        if ($this->input->isInteractive() && $subscription = $this->promptChooseSubscription($acquiaCloudClient)) {
            return $subscription;
        }

        throw new AcquiaCliException("Could not determine Cloud subscription. Run this command interactively or use the `subscriptionUuid` argument.");
    }

    /**
     * Determine the Cloud application.
     *
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    protected function determineCloudApplication(bool $promptLinkApp = false): ?string
    {
        $applicationUuid = $this->doDetermineCloudApplication();
        if (!isset($applicationUuid)) {
            throw new AcquiaCliException("Could not determine Cloud Application. Run this command interactively or use `acli link` to link a Cloud Application before running non-interactively.");
        }

        // No point in trying to link a directory that's not a repo.
        if (!empty($this->projectDir) && !$this->getCloudUuidFromDatastore()) {
            if ($promptLinkApp) {
                $application = $this->getCloudApplication($applicationUuid);
                $this->saveCloudUuidToDatastore($application);
            } elseif (!AcquiaDrupalEnvironmentDetector::isAhIdeEnv() && !$this->getCloudApplicationUuidFromBltYaml()) {
                $application = $this->getCloudApplication($applicationUuid);
                $this->promptLinkApplication($application);
            }
        }

        return $applicationUuid;
    }

    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    protected function doDetermineCloudApplication(): ?string
    {
        $acquiaCloudClient = $this->cloudApiClientService->getClient();

        if ($this->input->hasArgument('applicationUuid') && $this->input->getArgument('applicationUuid')) {
            $cloudApplicationUuid = $this->input->getArgument('applicationUuid');
            return self::validateUuid($cloudApplicationUuid);
        }

        if ($this->input->hasArgument('environmentId') && $this->input->getArgument('environmentId')) {
            $environmentId = $this->input->getArgument('environmentId');
            $environment = $this->getCloudEnvironment($environmentId);
            return $environment->application->uuid;
        }

        // Try local project info.
        if ($applicationUuid = $this->getCloudUuidFromDatastore()) {
            $this->logger->debug("Using Cloud application UUID: $applicationUuid from {$this->datastoreAcli->filepath}");
            return $applicationUuid;
        }

        if ($applicationUuid = $this->getCloudApplicationUuidFromBltYaml()) {
            $this->logger->debug("Using Cloud application UUID $applicationUuid from blt/blt.yml");
            return $applicationUuid;
        }

        // Get from the Cloud Platform env var.
        if ($applicationUuid = self::getCloudAppUuid()) {
            return $applicationUuid;
        }

        // Try to guess based on local git url config.
        if ($cloudApplication = $this->inferCloudAppFromLocalGitConfig($acquiaCloudClient)) {
            return $cloudApplication->uuid;
        }

        if ($this->input->isInteractive()) {
            /** @var ApplicationResponse $application */
            $application = $this->promptChooseApplication($acquiaCloudClient);
            if ($application) {
                return $application->uuid;
            }
        }

        return null;
    }

    /**
     * Determine the Cloud codebase.
     *
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    protected function determineCloudCodebase(): ?string
    {
        $codebaseUuid = $this->doDetermineCloudCodebase();
        if (!isset($codebaseUuid)) {
            throw new AcquiaCliException("Could not determine Cloud Codebase");
        }

        return $codebaseUuid;
    }

    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    protected function doDetermineCloudCodebase(): ?string
    {
        if ($this->input->hasArgument('codebaseId') && $this->input->getArgument('codebaseId')) {
            $cloudCodebaseId = $this->input->getArgument('codebaseId');
            return self::validateUuid($cloudCodebaseId);
        }

        if ($this->input->isInteractive()) {
            /** @var CodebaseResponse $codebase */
            $codebase = $this->promptChooseCodebase();
            if ($codebase != null) {
                return $codebase->id;
            }
        }

        return null;
    }

    /**
     * Prompts the user to choose from a list of available Cloud Platform
     * codebases.
     *
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    protected function promptChooseCodebase(): object|array|null
    {
        $customerCodebases = $this->getCloudCodebases();
        if (!$customerCodebases->count()) {
            throw new AcquiaCliException("You have no Cloud codebases.");
        }
        return $this->promptChooseFromObjectsOrArrays(
            $customerCodebases,
            'id',
            'label',
            'Select a Cloud Platform codebase:'
        );
    }

    protected function getCloudApplicationUuidFromBltYaml(): ?string
    {
        $bltYamlFilePath = Path::join($this->projectDir, 'blt', 'blt.yml');
        if (file_exists($bltYamlFilePath)) {
            $contents = Yaml::parseFile($bltYamlFilePath);
            if (array_key_exists('cloud', $contents) && array_key_exists('appId', $contents['cloud'])) {
                return $contents['cloud']['appId'];
            }
        }

        return null;
    }

    public static function validateUuid(string $uuid): string
    {
        $violations = Validation::createValidator()->validate($uuid, [
            new Length([
                'value' => 36,
            ]),
            self::getUuidRegexConstraint(),
        ]);
        if (count($violations)) {
            throw new ValidatorException($violations->get(0)->getMessage());
        }

        return $uuid;
    }

    private function saveCloudUuidToDatastore(ApplicationResponse $application): bool
    {
        $this->datastoreAcli->set('cloud_app_uuid', $application->uuid);
        $this->io->success("The Cloud application $application->name has been linked to this repository by writing to {$this->datastoreAcli->filepath}");

        return true;
    }
    protected function getCloudUuidFromDatastore(): ?string
    {
        return $this->datastoreAcli->get('cloud_app_uuid');
    }

    private function promptLinkApplication(
        ApplicationResponse $cloudApplication
    ): bool {
        $answer = $this->io->confirm("Would you like to link the Cloud application <bg=cyan;options=bold>$cloudApplication->name</> to this repository?");
        if ($answer) {
            return $this->saveCloudUuidToDatastore($cloudApplication);
        }
        return false;
    }

    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    protected function validateCwdIsValidDrupalProject(): void
    {
        if (!$this->projectDir) {
            throw new AcquiaCliException('Could not find a local Drupal project. Looked for `docroot/index.php` in current and parent directories. Execute this command from within a Drupal project directory.');
        }
    }

    /**
     * Determines if Acquia CLI is being run from within a Cloud IDE.
     *
     * @return bool TRUE if Acquia CLI is being run from within a Cloud IDE.
     */
    public static function isAcquiaCloudIde(): bool
    {
        return AcquiaDrupalEnvironmentDetector::isAhIdeEnv();
    }

    /**
     * Get the Cloud Application UUID from a Cloud IDE's environmental variable.
     *
     * This command assumes it is being run inside a Cloud IDE.
     */
    protected static function getCloudAppUuid(): false|string
    {
        return getenv('ACQUIA_APPLICATION_UUID');
    }

    /**
     * Get the Cloud Codebase UUID from a Cloud IDE's environmental variable.
     *
     * This command assumes it is being run inside a Cloud IDE.
     */
    protected static function getCodebaseUuid(): false|string
    {
        return getenv('AH_CODEBASE_UUID');
    }
    /**
     * Get the UUID from a Cloud IDE's environmental variable.
     *
     * This command assumes it is being run inside a Cloud IDE.
     */
    protected static function getThisCloudIdeUuid(): false|string
    {
        return getenv('REMOTEIDE_UUID');
    }

    protected static function getThisCloudIdeLabel(): false|string
    {
        return getenv('REMOTEIDE_LABEL');
    }

    protected static function getThisCloudIdeWebUrl(): false|string
    {
        return getenv('REMOTEIDE_WEB_HOST');
    }

    protected function getCloudApplication(string $applicationUuid): ApplicationResponse
    {
        $applicationsResource = new Applications($this->cloudApiClientService->getClient());
        return $applicationsResource->get($applicationUuid);
    }

    protected function getCloudCodebase(string $codebaseUuid): CodebaseResponse
    {
        $codebasesResource = new Codebases($this->cloudApiClientService->getClient());
        return $codebasesResource->get($codebaseUuid);
    }

    protected function getCloudCodebases(): CodebasesResponse
    {
        $codebasesResource = new Codebases($this->cloudApiClientService->getClient());
        return $codebasesResource->getAll();
    }

    protected function getCloudEnvironment(string $environmentId): EnvironmentResponse
    {
        $environmentResource = new Environments($this->cloudApiClientService->getClient());

        return $environmentResource->get($environmentId);
    }
    protected function getCodebaseEnvironment(string $environmentId): CodebaseEnvironmentResponse
    {
        $environmentResource = new CodebaseEnvironments($this->cloudApiClientService->getClient());
        $codebaseEnvironment = $environmentResource->getById($environmentId);
        return $codebaseEnvironment;
    }

    /**
     * Returns true if the application is running in a test environment.
     */
    protected function getCodebase(string $codebaseId): CodebaseResponse
    {
        $codebaseResource = new Codebases($this->cloudApiClientService->getClient());
        $codebase = $codebaseResource->get($codebaseId);

        return $codebase;
    }
    protected function getSite(string $siteId): SiteResponse
    {
        $siteResource = new Sites($this->cloudApiClientService->getClient());
        $site = $siteResource->get($siteId);
        return $site;
    }
    /**
     * Get the SiteInstances endpoint.
     */
    protected function getSiteInstance(string $siteId, string $environmentId): ?SiteInstanceResponse
    {
        try {
            $acquiaCloudClient = $this->cloudApiClientService->getClient();
            $siteInstancesResource = new SiteInstances($acquiaCloudClient);
            $siteInstance = $siteInstancesResource->get($siteId, $environmentId);
            return $siteInstance;
        } catch (\Exception $e) {
            $this->logger->debug("Site instance with ID $siteId.$environmentId not found." . $e->getMessage());
            return null;
        }
    }
    /**
     * Get the database for a site instance in a given environment.
     *
     * @param object|null $site (site object from getSitesByCodebase)
     * @return DatabaseResponse|null
     */
    private function getSiteInstanceDatabase(string $siteUuid, string $environmentUuid): ?SiteInstanceDatabaseResponse
    {
        try {
            $acquiaCloudClient = $this->cloudApiClientService->getClient();
            $siteInstancesResource = new SiteInstances($acquiaCloudClient);
            $siteInstanceDatabase = $siteInstancesResource->getDatabase($siteUuid, $environmentUuid);
            return $siteInstanceDatabase;
        } catch (\Exception $e) {
            $this->logger->debug('Could not get site instance database: ' . $e->getMessage());
        }
        return null;
    }

    public static function validateEnvironmentAlias(string $alias): string
    {
        $violations = Validation::createValidator()->validate($alias, [
            new Length(['min' => 5]),
            new NotBlank(),
            new Regex([
                'message' => 'You must enter either an environment ID or alias. Environment aliases must match the pattern [app-name].[env]',
                'pattern' => '/.+\..+/',
            ]),
        ]);
        if (count($violations)) {
            throw new ValidatorException($violations->get(0)->getMessage());
        }

        return $alias;
    }

    protected function normalizeAlias(string $alias): string
    {
        return str_replace('@', '', $alias);
    }

    /**
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function getEnvironmentFromAliasArg(string $alias): EnvironmentResponse
    {
        return $this->getEnvFromAlias($alias);
    }

    /**
     * @throws \Psr\Cache\InvalidArgumentException
     */
    private function getEnvFromAlias(string $alias): EnvironmentResponse
    {
        return self::getAliasCache()
            ->get($alias, function () use ($alias): EnvironmentResponse {
                return $this->doGetEnvFromAlias($alias);
            });
    }

    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    private function doGetEnvFromAlias(string $alias): EnvironmentResponse
    {
        $siteEnvParts = explode('.', $alias);
        [$applicationAlias, $environmentAlias] = $siteEnvParts;
        $this->logger->debug("Searching for an environment matching alias $applicationAlias.$environmentAlias.");
        $customerApplication = $this->getApplicationFromAlias($applicationAlias);
        $acquiaCloudClient = $this->cloudApiClientService->getClient();
        $environmentsResource = new Environments($acquiaCloudClient);
        $environments = $environmentsResource->getAll($customerApplication->uuid);
        foreach ($environments as $environment) {
            if ($environment->name === $environmentAlias) {
                $this->logger->debug("Found environment $environment->uuid matching $environmentAlias.");

                return $environment;
            }
        }

        throw new AcquiaCliException("Environment not found matching the alias {alias}", ['alias' => "$applicationAlias.$environmentAlias"]);
    }

    /**
     * @throws \Psr\Cache\InvalidArgumentException
     */
    private function getApplicationFromAlias(string $applicationAlias): mixed
    {
        return self::getAliasCache()
            ->get($applicationAlias, function () use ($applicationAlias) {
                return $this->doGetApplicationFromAlias($applicationAlias);
            });
    }

    /**
     * Return the ACLI alias cache.
     */
    public static function getAliasCache(): AliasCache
    {
        return new AliasCache('acli_aliases');
    }

    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    private function doGetApplicationFromAlias(string $applicationAlias): mixed
    {
        if (!strpos($applicationAlias, ':')) {
            $applicationAlias = '*:' . $applicationAlias;
        }
        $acquiaCloudClient = $this->cloudApiClientService->getClient();
        // No need to clear this query later since getClient() is a factory method.
        $acquiaCloudClient->addQuery('filter', 'hosting=@' . $applicationAlias);
        // Allow Cloud Platform users with 'support' role to resolve aliases for applications to
        // which they don't explicitly belong.
        $accountResource = new Account($acquiaCloudClient);
        $account = $accountResource->get();
        if ($account->flags->support) {
            $acquiaCloudClient->addQuery('all', 'true');
        }
        $applicationsResource = new Applications($acquiaCloudClient);
        $customerApplications = $applicationsResource->getAll();
        if (count($customerApplications) === 0) {
            throw new AcquiaCliException("No applications match the alias {applicationAlias}", ['applicationAlias' => $applicationAlias]);
        }
        if (count($customerApplications) > 1) {
            $callback = static function (mixed $element) {
                return $element->hosting->id;
            };
            $aliases = array_map($callback, (array) $customerApplications);
            $this->io->error(sprintf("Use a unique application alias: %s", implode(', ', $aliases)));
            throw new AcquiaCliException("Multiple applications match the alias {applicationAlias}", ['applicationAlias' => $applicationAlias]);
        }

        $customerApplication = $customerApplications[0];

        $this->logger->debug("Found application $customerApplication->uuid matching alias $applicationAlias.");

        return $customerApplication;
    }

    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    protected function requireCloudIdeEnvironment(): void
    {
        if (!self::isAcquiaCloudIde() || !self::getThisCloudIdeUuid()) {
            throw new AcquiaCliException('This command can only be run inside of an Acquia Cloud IDE');
        }
    }

    /**
     * @return \stdClass|null
     * @throws \AcquiaCloudApi\Exception\ApiErrorException
     */
    protected function findIdeSshKeyOnCloud(string $ideLabel, string $ideUuid): ?stdClass
    {
        $acquiaCloudClient = $this->cloudApiClientService->getClient();
        $cloudKeys = $acquiaCloudClient->request('get', '/account/ssh-keys');
        $sshKeyLabel = SshKeyCommandBase::getIdeSshKeyLabel($ideLabel, $ideUuid);
        foreach ($cloudKeys as $cloudKey) {
            if ($cloudKey->label === $sshKeyLabel) {
                return $cloudKey;
            }
        }
        return null;
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function checkForNewVersion(): bool|string
    {
        // Input not set if called from an exception listener.
        if (!isset($this->input)) {
            return false;
        }
        // Running on API commands would corrupt JSON output.
        if (
            str_contains($this->input->getArgument('command'), 'api:')
            || str_contains($this->input->getArgument('command'), 'acsf:')
        ) {
            return false;
        }
        // Bail in Cloud IDEs to avoid hitting GitHub API rate limits.
        if (AcquiaDrupalEnvironmentDetector::isAhIdeEnv()) {
            return false;
        }
        if ($this->getApplication()->getVersion() === 'dev-unknown') {
            return false;
        }
        try {
            if (!$this->selfUpdateManager->isUpToDate()) {
                return $this->selfUpdateManager->getLatestReleaseFromGithub()['tag_name'];
            }
        } catch (Exception) {
            $this->logger->debug("Could not determine if Acquia CLI has a new version available.");
        }
        return false;
    }

    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    protected function fillMissingRequiredApplicationUuid(InputInterface $input, OutputInterface $output): void
    {
        if (
            $input->hasArgument('applicationUuid') && !$input->getArgument('applicationUuid') && $this->getDefinition()
            ->getArgument('applicationUuid')
            ->isRequired()
        ) {
            $output->writeln('Inferring Cloud Application UUID for this command since none was provided...', OutputInterface::VERBOSITY_VERBOSE);
            if ($applicationUuid = $this->determineCloudApplication()) {
                $output->writeln("Set application uuid to <options=bold>$applicationUuid</>", OutputInterface::VERBOSITY_VERBOSE);
                $input->setArgument('applicationUuid', $applicationUuid);
            }
        }
    }

    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    private function convertUserAliasToUuid(InputInterface $input, string $userUuidArgument, string $orgUuidArgument): void
    {
        if (
            $input->hasArgument($userUuidArgument)
            && $input->getArgument($userUuidArgument)
            && $input->hasArgument($orgUuidArgument)
            && $input->getArgument($orgUuidArgument)
        ) {
            $userUuID = $input->getArgument($userUuidArgument);
            $orgUuid = $input->getArgument($orgUuidArgument);
            $userUuid = $this->validateUserUuid($userUuID, $orgUuid);
            $input->setArgument($userUuidArgument, $userUuid);
        }
    }

    /**
     * @param string $userUuidArgument User alias like uuid or email.
     * @param string $orgUuidArgument Organization uuid.
     * @return string User uuid from alias
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    private function validateUserUuid(string $userUuidArgument, string $orgUuidArgument): string
    {
        try {
            self::validateUuid($userUuidArgument);
        } catch (ValidatorException) {
            // Since this isn't a valid UUID, assuming this is email address.
            return $this->getUserUuidFromUserAlias($userUuidArgument, $orgUuidArgument);
        }

        return $userUuidArgument;
    }

    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    private static function getNotificationUuid(string $notification): string
    {
        // Greedily hope this is already a UUID.
        try {
            self::validateUuid($notification);
            return $notification;
        } catch (ValidatorException) {
        }

        // Not a UUID, maybe a JSON object?
        try {
            $json = json_decode($notification, null, 4, JSON_THROW_ON_ERROR);
            if (is_object($json)) {
                return self::getNotificationUuidFromResponse($json);
            }
            if (is_string($json)) {
                // In rare cases, JSON can decode to a string that's a valid UUID.
                self::validateUuid($json);
                return $json;
            }
        } catch (JsonException | AcquiaCliException | ValidatorException) {
        }

        // Last chance, maybe a URL?
        try {
            return self::getNotificationUuidFromUrl($notification);
        } catch (ValidatorException | AcquiaCliException) {
        }

        // Womp womp.
        throw new AcquiaCliException('Notification format is not one of UUID, JSON response, or URL');
    }

    /**
     * @param String $userAlias User alias like uuid or email.
     * @param String $orgUuidArgument Organization uuid.
     * @return string User uuid from alias
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    private function getUserUuidFromUserAlias(string $userAlias, string $orgUuidArgument): string
    {
        $acquiaCloudClient = $this->cloudApiClientService->getClient();
        $organizationResource = new Organizations($acquiaCloudClient);
        $orgMembers = $organizationResource->getMembers($orgUuidArgument);

        // If there are no members.
        if (count($orgMembers) === 0) {
            throw new AcquiaCliException('Organization has no members');
        }

        foreach ($orgMembers as $member) {
            // If email matches with any member.
            if ($member->mail === $userAlias) {
                return $member->uuid;
            }
        }

        throw new AcquiaCliException('No matching user found in this organization');
    }

    protected function convertApplicationAliasToUuid(InputInterface $input): void
    {
        if ($input->hasArgument('applicationUuid') && $input->getArgument('applicationUuid')) {
            $applicationUuidArgument = $input->getArgument('applicationUuid');
            $applicationUuid = $this->validateApplicationUuid($applicationUuidArgument);
            $input->setArgument('applicationUuid', $applicationUuid);
        }
    }

    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function convertEnvironmentAliasToUuid(InputInterface $input, string $argumentName): void
    {
        if ($input->hasArgument($argumentName) && $input->getArgument($argumentName)) {
            $envUuidArgument = $input->getArgument($argumentName);
            $environmentUuid = $this->validateEnvironmentUuid($envUuidArgument, $argumentName);
            $input->setArgument($argumentName, $environmentUuid);
        }
    }

    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    protected function convertNotificationToUuid(InputInterface $input, string $argumentName): void
    {
        if ($input->hasArgument($argumentName) && $input->getArgument($argumentName)) {
            $notificationArgument = $input->getArgument($argumentName);
            $notificationUuid = self::getNotificationUuid($notificationArgument);
            $input->setArgument($argumentName, $notificationUuid);
        }
    }

    public static function getSitegroup(EnvironmentResponse $environment): string
    {
        $sshUrlParts = explode('.', $environment->sshUrl);
        return reset($sshUrlParts);
    }

    public static function getEnvironmentAlias(EnvironmentResponse $environment): string
    {
        $sshUrlParts = explode('@', $environment->sshUrl);
        return reset($sshUrlParts);
    }

    protected function isAcsfEnv(mixed $cloudEnvironment): bool
    {
        if (str_contains($cloudEnvironment->sshUrl, 'enterprise-g1')) {
            return true;
        }
        foreach ($cloudEnvironment->domains as $domain) {
            if (str_contains($domain, 'acsitefactory')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<mixed>
     * @throws \Acquia\Cli\Exception\AcquiaCliException|\JsonException
     */
    private function getAcsfSites(EnvironmentResponse $cloudEnvironment): array
    {
        $envAlias = self::getEnvironmentAlias($cloudEnvironment);
        $command = ['cat', "/var/www/site-php/$envAlias/multisite-config.json"];
        $process = $this->sshHelper->executeCommand($cloudEnvironment->sshUrl, $command, false);
        if ($process->isSuccessful()) {
            return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        }
        throw new AcquiaCliException("Could not get ACSF sites");
    }

    /**
     * @return array<mixed>
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    private function getCloudSites(EnvironmentResponse $cloudEnvironment): array
    {
        $sitegroup = self::getSitegroup($cloudEnvironment);
        $command = [
            'ls',
            $this->getCloudSitesPath($cloudEnvironment, $sitegroup),
        ];
        $process = $this->sshHelper->executeCommand($cloudEnvironment->sshUrl, $command, false);
        $sites = array_filter(explode("\n", trim($process->getOutput())));
        if ($sites && $process->isSuccessful()) {
            if ($key = array_search('default', $sites, true)) {
                unset($sites[$key]);
                array_unshift($sites, 'default');
            }
            return $sites;
        }

        throw new AcquiaCliException("Could not get Cloud sites for " . $cloudEnvironment->name);
    }

    private function getCloudSitesPath(mixed $cloudEnvironment, mixed $sitegroup): string
    {
        if ($cloudEnvironment->platform === 'cloud-next') {
            $path = "/home/clouduser/$cloudEnvironment->name/sites";
        } elseif ($cloudEnvironment->platform === 'MEO') {
            $path = "/mnt/files/$cloudEnvironment->name/sites";
        } else {
            $path = "/mnt/files/$sitegroup.$cloudEnvironment->name/sites";
        }
        return $path;
    }

    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     * @throws \JsonException
     */
    private function promptChooseAcsfSite(EnvironmentResponse $cloudEnvironment): mixed
    {
        $choices = [];
        $acsfSites = $this->getAcsfSites($cloudEnvironment);
        foreach ($acsfSites['sites'] as $domain => $acsfSite) {
            $choices[] = "{$acsfSite['name']} ($domain)";
        }
        if (!count($choices)) {
            throw new AcquiaCliException('No sites found in this environment');
        }
        $choice = $this->io->choice('Choose a site', $choices, $choices[0]);
        $key = array_search($choice, $choices, true);
        $sites = array_values($acsfSites['sites']);
        $site = $sites[$key];

        return $site['name'];
    }

    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    protected function promptChooseCloudSite(EnvironmentResponse $cloudEnvironment): mixed
    {
        $sites = $this->getCloudSites($cloudEnvironment);
        if (count($sites) === 1) {
            $site = reset($sites);
            $this->logger->debug("Only a single Cloud site was detected. Assuming site is $site");
            return $site;
        }
        $this->logger->debug("Multisite detected");
        $this->warnMultisite();
        return $this->io->choice('Choose a site', $sites, $sites[0]);
    }

    protected static function isLandoEnv(): bool
    {
        return AcquiaDrupalEnvironmentDetector::isLandoEnv();
    }

    protected function reAuthenticate(string $apiKey, string $apiSecret, ?string $baseUri, ?string $accountsUri): void
    {
        // Client service needs to be reinitialized with new credentials in case
        // this is being run as a sub-command.
        // @see https://github.com/acquia/cli/issues/403
        $this->cloudApiClientService->setConnector(new Connector([
            'key' => $apiKey,
            'secret' => $apiSecret,
        ], $baseUri, $accountsUri));
    }

    private function warnMultisite(): void
    {
        $this->io->note("This is a multisite application. Drupal will load the default site unless you've configured sites.php for this environment: https://docs.acquia.com/cloud-platform/develop/drupal/multisite/");
    }

    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    protected function setDirAndRequireProjectCwd(InputInterface $input): void
    {
        $this->determineDir($input);
        if ($this->dir !== '/home/ide/project' && AcquiaDrupalEnvironmentDetector::isAhIdeEnv()) {
            throw new AcquiaCliException('Run this command from the {dir} directory', ['dir' => '/home/ide/project']);
        }
    }

    protected function determineDir(InputInterface $input): void
    {
        if (isset($this->dir)) {
            return;
        }

        if ($input->hasOption('dir') && $dir = $input->getOption('dir')) {
            $this->dir = $dir;
        } elseif ($this->projectDir) {
            $this->dir = $this->projectDir;
        } else {
            $this->dir = getcwd();
        }
    }

    protected function getOutputCallback(OutputInterface $output, Checklist $checklist): Closure
    {
        return static function (mixed $type, mixed $buffer) use ($checklist, $output): void {
            if (!$output->isVerbose() && $checklist->getItems()) {
                $checklist->updateProgressBar($buffer);
            }
            $output->writeln($buffer, OutputInterface::VERBOSITY_VERY_VERBOSE);
        };
    }

    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    protected function executeAllScripts(Closure $outputCallback, Checklist $checklist): void
    {
        $this->runComposerScripts($outputCallback, $checklist);
        $this->runDrushCacheClear($outputCallback, $checklist);
        $this->runDrushSqlSanitize($outputCallback, $checklist);
    }

    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    protected function runComposerScripts(callable $outputCallback, Checklist $checklist): void
    {
        if (!file_exists(Path::join($this->dir, 'composer.json'))) {
            $this->io->note('composer.json file not found. Skipping composer install.');
            return;
        }
        if (!$this->localMachineHelper->commandExists('composer')) {
            $this->io->note('Composer not found. Skipping composer install.');
            return;
        }
        if (file_exists(Path::join($this->dir, 'vendor'))) {
            $this->io->note('Composer dependencies already installed. Skipping composer install.');
            return;
        }
        $checklist->addItem("Installing Composer dependencies");
        $this->composerInstall($outputCallback);
        $checklist->completePreviousItem();
    }

    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    protected function runDrushCacheClear(Closure $outputCallback, Checklist $checklist): void
    {
        if ($this->getDrushDatabaseConnectionStatus()) {
            $checklist->addItem('Clearing Drupal caches via Drush');
            // @todo Add support for Drush 8.
            $process = $this->localMachineHelper->execute([
                'drush',
                'cache:rebuild',
                '--yes',
                '--no-interaction',
                '--verbose',
            ], $outputCallback, $this->dir, false);
            if (!$process->isSuccessful()) {
                throw new AcquiaCliException('Unable to rebuild Drupal caches via Drush. {message}', ['message' => $process->getErrorOutput()]);
            }
            $checklist->completePreviousItem();
        } else {
            $this->logger->notice('Drush does not have an active database connection. Skipping cache:rebuild');
        }
    }

    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    protected function runDrushSqlSanitize(Closure $outputCallback, Checklist $checklist): void
    {
        if ($this->getDrushDatabaseConnectionStatus()) {
            $checklist->addItem('Sanitizing database via Drush');
            $process = $this->localMachineHelper->execute([
                'drush',
                'sql:sanitize',
                '--yes',
                '--no-interaction',
                '--verbose',
            ], $outputCallback, $this->dir, false);
            if (!$process->isSuccessful()) {
                throw new AcquiaCliException('Unable to sanitize Drupal database via Drush. {message}', ['message' => $process->getErrorOutput()]);
            }
            $checklist->completePreviousItem();
            $this->io->newLine();
            $this->io->text('Your database was sanitized via <options=bold>drush sql:sanitize</>. This has changed all user passwords to randomly generated strings. To log in to your Drupal site, use <options=bold>drush uli</>');
        } else {
            $this->logger->notice('Drush does not have an active database connection. Skipping sql:sanitize.');
        }
    }

    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    private function composerInstall(?callable $outputCallback): void
    {
        $process = $this->localMachineHelper->execute([
            'composer',
            'install',
            '--no-interaction',
        ], $outputCallback, $this->dir, false);
        if (!$process->isSuccessful()) {
            throw new AcquiaCliException(
                'Unable to install Drupal dependencies via Composer. {message}',
                ['message' => $process->getErrorOutput()]
            );
        }
    }

    protected function getDrushDatabaseConnectionStatus(?Closure $outputCallback = null): bool
    {
        if (isset($this->drushHasActiveDatabaseConnection)) {
            return $this->drushHasActiveDatabaseConnection;
        }
        if ($this->localMachineHelper->commandExists('drush')) {
            $process = $this->localMachineHelper->execute([
                'drush',
                'status',
                '--fields=db-status,drush-version',
                '--format=json',
                '--no-interaction',
            ], $outputCallback, $this->dir, false);
            if ($process->isSuccessful()) {
                $drushStatusReturnOutput = json_decode($process->getOutput(), true);
                if (is_array($drushStatusReturnOutput) && array_key_exists('db-status', $drushStatusReturnOutput) && $drushStatusReturnOutput['db-status'] === 'Connected') {
                    $this->drushHasActiveDatabaseConnection = true;
                    return $this->drushHasActiveDatabaseConnection;
                }
            }
        }

        $this->drushHasActiveDatabaseConnection = false;

        return $this->drushHasActiveDatabaseConnection;
    }

    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    protected function createMySqlDumpOnLocal(string $dbHost, string $dbUser, string $dbName, string $dbPassword, ?Closure $outputCallback = null): string
    {
        $this->localMachineHelper->checkRequiredBinariesExist([
            'mysqldump',
            'gzip',
        ]);
        $filename = "acli-mysql-dump-$dbName.sql.gz";
        $localTempDir = sys_get_temp_dir();
        $localFilepath = $localTempDir . '/' . $filename;
        $this->logger->debug("Dumping MySQL database to $localFilepath on this machine");
        $this->localMachineHelper->checkRequiredBinariesExist([
            'mysqldump',
            'gzip',
        ]);
        if ($outputCallback) {
            $outputCallback('out', "Dumping MySQL database to $localFilepath on this machine");
        }
        if ($this->localMachineHelper->commandExists('pv')) {
            $command = "bash -c \"set -o pipefail; MYSQL_PWD=$dbPassword mysqldump --host=$dbHost --user=$dbUser $dbName | pv --rate --bytes | gzip -9 > $localFilepath\"";
        } else {
            $this->io->warning('Install `pv` to see progress bar');
            $command = "bash -c \"set -o pipefail; MYSQL_PWD=$dbPassword mysqldump --host=$dbHost --user=$dbUser $dbName | gzip -9 > $localFilepath\"";
        }

        $process = $this->localMachineHelper->executeFromCmd($command, $outputCallback, null, ($this->output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL));
        if (!$process->isSuccessful() || $process->getOutput()) {
            throw new AcquiaCliException('Unable to create a dump of the local database. {message}', ['message' => $process->getErrorOutput()]);
        }

        return $localFilepath;
    }

    /** @infection-ignore-all */
    protected function promptOpenBrowserToCreateToken(
        InputInterface $input
    ): void {
        if (!$input->getOption('key') || !$input->getOption('secret')) {
            $tokenUrl = 'https://cloud.acquia.com/a/profile/tokens';
            $this->output->writeln("You will need a Cloud Platform API token from <href=$tokenUrl>$tokenUrl</>");

            if (!AcquiaDrupalEnvironmentDetector::isAhIdeEnv() && $this->io->confirm('Do you want to open this page to generate a token now?')) {
                $this->localMachineHelper->startBrowser($tokenUrl);
            }
        }
    }

    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    protected function determineApiKey(): string
    {
        return $this->determineOption('key', false, $this->validateApiKey(...));
    }

    protected static function validateApiKey(mixed $key): string
    {
        $violations = Validation::createValidator()->validate($key, [
            new Length(['min' => 10]),
            new NotBlank(),
            new Regex([
                'message' => 'The value may not contain spaces',
                'pattern' => '/^\S*$/',
            ]),
        ]);
        if (count($violations)) {
            throw new ValidatorException($violations->get(0)->getMessage());
        }
        return $key;
    }

    protected static function validateUrl(?string $url): string
    {
        $violations = Validation::createValidator()->validate($url, [
            new NotBlank(),
            new Url(),
        ]);
        if (count($violations)) {
            throw new ValidatorException($violations->get(0)->getMessage());
        }
        return $url;
    }

    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    protected function determineApiSecret(): string
    {
        return $this->determineOption('secret', true, $this->validateApiKey(...));
    }

    /**
     * Get an option, either passed explicitly or via interactive prompt.
     *
     * Default can be passed explicitly, separately from the option default,
     * because Symfony does not make a distinction between an option value set
     * explicitly or by default. In other words, we can't prompt for the value
     * of an option that already has a default value.
     */
    protected function determineOption(string $optionName, bool $hidden = false, ?Closure $validator = null, ?Closure $normalizer = null, string|bool|null $default = null): string|int|bool|null
    {
        if ($optionValue = $this->input->getOption($optionName)) {
            if (isset($normalizer)) {
                $optionValue = $normalizer($optionValue);
            }
            if (isset($validator)) {
                $validator($optionValue);
            }
            return $optionValue;
        }
        $option = $this->getDefinition()->getOption($optionName);
        if ($option->isNegatable() && $this->input->getOption("no-$optionName")) {
            return false;
        }
        $optionShortcut = $option->getShortcut();
        $description = lcfirst($option->getDescription());
        if ($optionShortcut) {
            $optionString = "option <options=bold>-$optionShortcut</>, <options=bold>--$optionName</>";
        } else {
            $optionString = "option <options=bold>--$optionName</>";
        }
        if ($option->acceptValue()) {
            $message = "Enter $description ($optionString)";
        } else {
            $message = "Do you want to $description ($optionString)?";
        }
        $optional = $option->isValueOptional();
        $message .= $optional ? ' (optional)' : '';
        $message .= $hidden ? ' (input will be hidden)' : '';
        if ($option->acceptValue()) {
            $question = new Question($message, $default);
        } else {
            $question = new ConfirmationQuestion($message, $default);
        }
        $question->setHidden($this->localMachineHelper->useTty() && $hidden);
        $question->setHiddenFallback($hidden);
        if (isset($normalizer)) {
            $question->setNormalizer($normalizer);
        }
        if (isset($validator)) {
            $question->setValidator($validator);
        }
        $optionValue = $this->io->askQuestion($question);
        // Question bypasses validation if session is non-interactive.
        if (!$optional && is_null($optionValue)) {
            throw new AcquiaCliException($message);
        }
        return $optionValue;
    }

    /**
     * Get the first environment for a given Cloud application matching a
     * filter.
     */
    private function getAnyAhEnvironment(string $cloudAppUuid, callable $filter): EnvironmentResponse|false
    {
        $acquiaCloudClient = $this->cloudApiClientService->getClient();
        $environmentResource = new Environments($acquiaCloudClient);
        /** @var \Twig\EnvironmentResponse[] $applicationEnvironments */
        $applicationEnvironments = iterator_to_array($environmentResource->getAll($cloudAppUuid));
        $candidates = array_filter($applicationEnvironments, $filter);
        return reset($candidates);
    }

    /**
     * Get the first non-prod environment for a given Cloud application.
     */
    protected function getAnyNonProdAhEnvironment(string $cloudAppUuid): EnvironmentResponse|false
    {
        return $this->getAnyAhEnvironment($cloudAppUuid, function (EnvironmentResponse $environment) {
            return !$environment->flags->production && $environment->type === 'drupal';
        });
    }

    /**
     * Get the first prod environment for a given Cloud application.
     */
    protected function getAnyProdAhEnvironment(string $cloudAppUuid): EnvironmentResponse|false
    {
        return $this->getAnyAhEnvironment($cloudAppUuid, function (EnvironmentResponse $environment) {
            return $environment->flags->production && $environment->type === 'drupal';
        });
    }
    protected function determineVcsUrl(InputInterface $input, OutputInterface $output, string $applicationUuid): array|false
    {
        if ($input->hasOption('siteInstanceId') && $input->getOption('siteInstanceId')) {
            $siteInstance = $this->determineSiteInstance($input);
            $vcsUrl = $siteInstance->environment->codebase->vcs_url ?? $this->getAnyVcsUrl($applicationUuid);
            return [$vcsUrl];
        } elseif ($vcsUrl = $this->getAnyVcsUrl($applicationUuid)) {
            return [$vcsUrl];
        }
        $output->writeln('No VCS URL found for this application. Please provide one with the --vcs-url option.');
        return false;
    }
    /**
     * Get the first VCS URL for a given Cloud application.
     */
    protected function getAnyVcsUrl(string $cloudAppUuid): string|false
    {
        $environment = $this->getAnyAhEnvironment($cloudAppUuid, function (): bool {
            return true;
        });
        return $environment ? $environment->vcs->url : false;
    }

    protected function validateApplicationUuid(string $applicationUuidArgument): mixed
    {
        try {
            self::validateUuid($applicationUuidArgument);
        } catch (ValidatorException) {
            // Since this isn't a valid UUID, let's see if it's a valid alias.
            $alias = $this->normalizeAlias($applicationUuidArgument);
            return $this->getApplicationFromAlias($alias)->uuid;
        }
        return $applicationUuidArgument;
    }

    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function validateEnvironmentUuid(mixed $envUuidArgument, mixed $argumentName): string
    {
        if (is_null($envUuidArgument)) {
            throw new AcquiaCliException("{{$argumentName}} must not be null");
        }
        try {
            // Environment IDs take the form of [env-num]-[app-uuid].
            $uuidParts = explode('-', $envUuidArgument);
            unset($uuidParts[0]);
            $applicationUuid = implode('-', $uuidParts);
            self::validateUuid($applicationUuid);
        } catch (ValidatorException) {
            try {
                // Since this isn't a valid environment ID, let's see if it's a valid alias.
                $alias = $envUuidArgument;
                $alias = $this->normalizeAlias($alias);
                $alias = self::validateEnvironmentAlias($alias);
                return $this->getEnvironmentFromAliasArg($alias)->uuid;
            } catch (AcquiaCliException) {
                throw new AcquiaCliException("{{$argumentName}} must be a valid UUID or site alias.");
            }
        }
        return $envUuidArgument;
    }

    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    protected function checkAuthentication(): void
    {
        if ((new ReflectionClass(static::class))->getAttributes(RequireAuth::class) && !$this->cloudApiClientService->isMachineAuthenticated()) {
            if ($this->cloudApiClientService instanceof AcsfClientService) {
                throw new AcquiaCliException('This machine is not yet authenticated with Site Factory.');
            }
            throw new AcquiaCliException('This machine is not yet authenticated with the Cloud Platform.');
        }
    }

    protected function waitForNotificationToComplete(Client $acquiaCloudClient, string $uuid, string $message, ?callable $success = null): bool
    {
        $notificationsResource = new Notifications($acquiaCloudClient);
        $notification = null;
        $checkNotificationStatus = static function () use ($notificationsResource, &$notification, $uuid): bool {
            $notification = $notificationsResource->get($uuid);
            // @infection-ignore-all
            return $notification->status !== 'in-progress';
        };
        if ($success === null) {
            $success = function () use (&$notification): void {
                $this->writeCompletedMessage($notification);
            };
        }
        LoopHelper::getLoopy($this->output, $this->io, $message, $checkNotificationStatus, $success);
        return $notification->status === 'completed';
    }

    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    private function writeCompletedMessage(NotificationResponse $notification): void
    {
        if ($notification->status === 'completed') {
            $this->io->success("The task with notification uuid $notification->uuid completed");
        } elseif ($notification->status === 'failed') {
            $this->io->error("The task with notification uuid $notification->uuid failed");
        } else {
            throw new AcquiaCliException("Unknown task status: $notification->status");
        }
        $duration = strtotime($notification->completed_at) - strtotime($notification->created_at);
        $completedAt = date("D M j G:i:s T Y", strtotime($notification->completed_at));
        $this->io->writeln("Progress: $notification->progress");
        $this->io->writeln("Completed: $completedAt");
        $this->io->writeln("Task type: $notification->label");
        $this->io->writeln("Duration: $duration seconds");
    }

    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    protected static function getNotificationUuidFromResponse(object $response): string
    {
        if (property_exists($response, 'links')) {
            $links = $response->links;
        } elseif (property_exists($response, '_links')) {
            $links = $response->_links;
        } else {
            throw new AcquiaCliException('JSON object must contain the _links.notification.href property');
        }
        if (property_exists($links, 'notification') && property_exists($links->notification, 'href')) {
            return self::getNotificationUuidFromUrl($links->notification->href);
        }
        throw new AcquiaCliException('JSON object must contain the _links.notification.href property');
    }

    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     */
    private static function getNotificationUuidFromUrl(string $notificationUrl): string
    {
        $notificationUrlPattern = '/^https:\/\/cloud.acquia.com\/api\/notifications\/([\w-]*)$/';
        if (preg_match($notificationUrlPattern, $notificationUrl, $matches)) {
            self::validateUuid($matches[1]);
            return $matches[1];
        }
        throw new AcquiaCliException('Notification UUID not found in URL');
    }

    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     * @throws \AcquiaCloudApi\Exception\ApiErrorException
     */
    protected function validateRequiredCloudPermissions(Client $acquiaCloudClient, ?string $cloudApplicationUuid, AccountResponse $account, array $requiredPermissions): void
    {
        $permissions = $acquiaCloudClient->request('get', "/applications/$cloudApplicationUuid/permissions");
        $keyedPermissions = [];
        foreach ($permissions as $permission) {
            $keyedPermissions[$permission->name] = $permission;
        }
        foreach ($requiredPermissions as $name) {
            if (!array_key_exists($name, $keyedPermissions)) {
                throw new AcquiaCliException("The Acquia Cloud Platform account {account} does not have the required '{name}' permission. Add the permissions to this user or use an API Token belonging to a different Acquia Cloud Platform user.", [
                    'account' => $account->mail,
                    'name' => $name,
                ]);
            }
        }
    }

    protected function validatePhpVersion(string $version): string
    {
        $violations = Validation::createValidator()->validate($version, [
            new Length(['min' => 3]),
            new NotBlank(),
            new Regex([
                'message' => 'The value may not contain spaces',
                'pattern' => '/^\S*$/',
            ]),
            new Regex([
                'message' => 'The value must be in the format "x.y"',
                'pattern' => '/[0-9]{1}\.[0-9]{1}/',
            ]),
        ]);
        if (count($violations)) {
            throw new ValidatorException($violations->get(0)->getMessage());
        }

        return $version;
    }

    /**
     * @throws \Acquia\Cli\Exception\AcquiaCliException
     * @throws \JsonException
     */
    protected function promptChooseDrupalSite(EnvironmentResponse $environment): string
    {
        if ($this->isAcsfEnv($environment)) {
            return $this->promptChooseAcsfSite($environment);
        }

        return $this->promptChooseCloudSite($environment);
    }
}
