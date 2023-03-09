<?php

namespace Acquia\Cli\Command\Env;

use Acquia\Cli\Command\CommandBase;
use AcquiaCloudApi\Endpoints\Crons;
use Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class EnvCopyCronCommand.
 */
class EnvCopyCronCommand extends CommandBase {

  protected static $defaultName = 'env:cron-copy';

  /**
   * {inheritdoc}.
   */
  protected function configure(): void {
    $this->setDescription('Copy all cron tasks from one Acquia Cloud Platform environment to another')
      ->addArgument('source_env', InputArgument::REQUIRED, 'Alias of the source environment in the format `app-name.env` or the environment uuid')
      ->addArgument('dest_env', InputArgument::REQUIRED, 'Alias of the destination environment in the format `app-name.env` or the environment uuid')
      ->addUsage(self::getDefaultName() . ' <srcEnvironmentAlias> <destEnvironmentAlias>')
      ->addUsage(self::getDefaultName() . ' myapp.dev myapp.prod')
      ->addUsage(self::getDefaultName() . ' abcd1234-1111-2222-3333-0e02b2c3d470 efgh1234-1111-2222-3333-0e02b2c3d470');
  }

  /**
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Exception|\Psr\Cache\InvalidArgumentException
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    // If both source and destination env inputs are same.
    if ($input->getArgument('source_env') === $input->getArgument('dest_env')) {
      $this->io->error('The source and destination environments can not be same.');
      return 1;
    }

    // Get source env alias.
    $this->convertEnvironmentAliasToUuid($input, 'source_env');
    $source_env_id = $input->getArgument('source_env');

    // Get destination env alias.
    $this->convertEnvironmentAliasToUuid($input, 'dest_env');
    $dest_env_id = $input->getArgument('dest_env');

    // Get the cron resource.
    $cron_resource = new Crons($this->cloudApiClientService->getClient());
    $source_env_cron_list = $cron_resource->getAll($source_env_id);

    // Ask for confirmation before starting the copy.
    $answer = $this->io->confirm('Are you sure you\'d like to copy the cron jobs from ' . $source_env_id . ' to ' . $dest_env_id . '?');
    if (!$answer) {
      return 0;
    }

    $only_system_crons = TRUE;
    foreach ($source_env_cron_list as $cron) {
      if (!$cron->flags->system) {
        $only_system_crons = FALSE;
      }
    }

    // If source environment doesn't have any cron job or only
    // has system crons.
    if ($only_system_crons || $source_env_cron_list->count() === 0) {
      $this->io->error('There are no cron jobs in the source environment for copying.');
      return 1;
    }

    foreach ($source_env_cron_list as $cron) {
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

        }
        catch (Exception $e) {
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
