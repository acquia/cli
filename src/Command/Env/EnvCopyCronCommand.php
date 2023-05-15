<?php

namespace Acquia\Cli\Command\Env;

use Acquia\Cli\Command\CommandBase;
use AcquiaCloudApi\Endpoints\Crons;
use Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class EnvCopyCronCommand extends CommandBase {

  protected static $defaultName = 'env:cron-copy';

  protected function configure(): void {
    $this->setDescription('Copy all cron tasks from one Acquia Cloud Platform environment to another')
      ->addArgument('source_env', InputArgument::REQUIRED, 'Alias of the source environment in the format `app-name.env` or the environment uuid')
      ->addArgument('dest_env', InputArgument::REQUIRED, 'Alias of the destination environment in the format `app-name.env` or the environment uuid')
      ->addUsage(self::getDefaultName() . ' <srcEnvironmentAlias> <destEnvironmentAlias>')
      ->addUsage(self::getDefaultName() . ' myapp.dev myapp.prod')
      ->addUsage(self::getDefaultName() . ' abcd1234-1111-2222-3333-0e02b2c3d470 efgh1234-1111-2222-3333-0e02b2c3d470');
  }

  /**
   * @return int 0 if everything went fine, or an exit code
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    // If both source and destination env inputs are same.
    if ($input->getArgument('source_env') === $input->getArgument('dest_env')) {
      $this->io->error('The source and destination environments can not be same.');
      return 1;
    }

    // Get source env alias.
    $this->convertEnvironmentAliasToUuid($input, 'source_env');
    $sourceEnvId = $input->getArgument('source_env');

    // Get destination env alias.
    $this->convertEnvironmentAliasToUuid($input, 'dest_env');
    $destEnvId = $input->getArgument('dest_env');

    // Get the cron resource.
    $cronResource = new Crons($this->cloudApiClientService->getClient());
    $sourceEnvCronList = $cronResource->getAll($sourceEnvId);

    // Ask for confirmation before starting the copy.
    $answer = $this->io->confirm('Are you sure you\'d like to copy the cron jobs from ' . $sourceEnvId . ' to ' . $destEnvId . '?');
    if (!$answer) {
      return 0;
    }

    $onlySystemCrons = TRUE;
    foreach ($sourceEnvCronList as $cron) {
      if (!$cron->flags->system) {
        $onlySystemCrons = FALSE;
      }
    }

    // If source environment doesn't have any cron job or only
    // has system crons.
    if ($onlySystemCrons || $sourceEnvCronList->count() === 0) {
      $this->io->error('There are no cron jobs in the source environment for copying.');
      return 1;
    }

    foreach ($sourceEnvCronList as $cron) {
      // We don't copy the system cron as those should already be there
      // when environment is provisioned.
      if (!$cron->flags->system) {
        $cronFrequency = implode(' ', [
          $cron->minute,
          $cron->hour,
          $cron->dayMonth,
          $cron->month,
          $cron->dayWeek,
        ]);

        $this->io->info('Copying the cron task "' . $cron->label . '" from ' . $sourceEnvId . ' to ' . $destEnvId);
        try {
          // Copying the cron on destination environment.
          $cronResource->create(
            $destEnvId,
            $cron->command,
            $cronFrequency,
            $cron->label,
          );

        }
        catch (Exception $e) {
          $this->io->error('There was some error while copying the cron task "' . $cron->label . '"');
          // Log the error for debugging purpose.
          $this->logger->debug('Error @error while copying the cron task @cron from @source env to @dest env', [
            '@cron' => $cron->label,
            '@dest' => $destEnvId,
            '@error' => $e->getMessage(),
            '@source' => $sourceEnvId,
          ]);
          return 1;
        }
      }
    }

    $this->io->success('Cron task copy is completed.');
    return 0;
  }

}
