<?php

namespace Acquia\Cli\Command\Ide\Wizard;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Output\Checklist;
use Acquia\Cli\Output\Spinner\Spinner;
use AcquiaCloudApi\Endpoints\Ides;
use AcquiaCloudApi\Response\IdeResponse;
use AcquiaCloudApi\Response\OperationResponse;
use Exception;
use GuzzleHttp\Client;
use React\EventLoop\Factory;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * Class IdeWizardCommand.
 */
class IdeWizardCommand extends IdeWizardCommandBase {

  /**
   * The default command name.
   *
   * @var string
   */
  protected static $defaultName = 'ide:wizard';

  /**
   * @var \GuzzleHttp\Client
   */
  private $client;

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Wizard to perform first time setup tasks within an IDE');
    // The IDE wizard namespace makes two key assumptions.
    // 1. The command is running inside of an Acquia Remote IDE.
    // 2. Consequently, we know the associated Acquia Cloud application already.
    // It also follows a different UX principal: don't ask the user.
    // It automatically generates values for SSH key filename, password, and
    // label.
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    // Create SSH key.
    // Upload SSH key.
    // Refresh from environment.
  }

}
