<?php

namespace Acquia\Cli\Command;

use AcquiaCloudApi\Endpoints\Crons;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class CopyCronTasksCommand.
 */
class CopyCronTasksCommand extends CommandBase {

  protected static $defaultName = 'app:cron-copy';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('Copy all cron tasks from one Acquia Cloud Platform environment to another')
      ->addArgument('source_app', InputArgument::REQUIRED, 'Alias of the source application in the format `app-name.env` or the environment uuid')
      ->addArgument('dest_app', InputArgument::REQUIRED, 'Alias of the destination application in the format `app-name.env` or the environment uuid')
      ->addUsage(self::getDefaultName() . ' <srcApplicationAlias> <destApplicationAlias>')
      ->addUsage(self::getDefaultName() . ' myapp.dev myapp.prod')
      ->addUsage(self::getDefaultName() . ' abcd1234-1111-2222-3333-0e02b2c3d470 efgh1234-1111-2222-3333-0e02b2c3d470');
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    // If both source and destination app inputs are same.
    if ($input->getArgument('source_app') === $input->getArgument('dest_app')) {
      $this->io->error('The source and destination environments can not be same.');
      return 1;
    }

    // Get source app alias.
    $this->convertEnvironmentAliasToUuid($input, 'source_app');
    $source_env_id = $input->getArgument('source_app');

    // Get destination app alias.
    $this->convertEnvironmentAliasToUuid($input, 'dest_app');
    $dest_env_id = $input->getArgument('dest_app');

    // Get the cron resource.
    $cron_resource = new Crons($this->cloudApiClientService->getClient());
    $source_app_cron_list = $cron_resource->getAll($source_env_id);

    // Ask for confirmation before starting the copy.
    $answer = $this->io->confirm('Are you sure you\'d like to copy the cron jobs from ' . $source_env_id . ' to ' . $dest_env_id . '?');
    if (!$answer) {
      return 0;
    }

    $only_system_crons = TRUE;
    foreach ($source_app_cron_list as $cron) {
      if (!$cron->flags->system) {
        $only_system_crons = FALSE;
      }
    }

    // If source environment doesn't have any cron job or only
    // has system crons.
    if ($source_app_cron_list->count() === 0 || $only_system_crons) {
      $this->io->error('There are no cron jobs in the source environment for copying.');
      return 1;
    }

    foreach ($source_app_cron_list as $cron) {
      // We don't copy the system cron as those should already be there
      // when environment is provisioned.
      if (!$cron->flags->system) {
        $cron_frequency = implode(' ', [
          $cron->minute,
          $cron->hour,
          $cron->dayMonth,
          $cron->month,
          $cron->dayWeek,
        ]);

        $this->io->info('Copying the cron task "' . $cron->label . '" from ' . $source_env_id . ' to ' . $dest_env_id);
        try {
          // Copying the cron on destination environment.
          $cron_resource->create(
            $dest_env_id,
            $cron->command,
            $cron_frequency,
            $cron->label,
          );

        } catch (\Exception $e) {
          $this->io->error('There was some error while copying the cron task "' . $cron->label . '"');
          // Log the error for debugging purpose.
          $this->logger->debug('Error @error while copying the cron task @cron from @source env to @dest env', [
            '@cron' => $cron->label,
            '@source' => $source_env_id,
            '@dest' => $dest_env_id,
            '@error' => $e->getMessage(),
          ]);
          return 1;
        }
      }
    }

    $this->io->success('Cron task copy is completed.');
    return 0;
  }

}
