<?php

declare(strict_types = 1);

namespace Acquia\Cli\Command\Pipeline;

use Acquia\Cli\Attribute\RequireAuth;
use Acquia\Cli\Command\CommandBase;
use GuzzleHttp\Client as GuzzleClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[RequireAuth]
#[AsCommand(name: 'pipeline:encrypt-var', description: 'Get the encrypted text to be used for pipeline')]
final class PipelineEncryptVariables extends CommandBase {

  protected function configure(): void {
    $this->addArgument('encrypting_string', InputArgument::REQUIRED, 'Text that to be encrypted');
    $this->addArgument('applicationUuid', InputArgument::REQUIRED, 'The Cloud Platform application UUID or alias (i.e. an application name optionally prefixed with the realm)');
    $this->addUsage('SOMESTRINGTOENCRYPT APPUUIDHERE');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $cloudApplicationUuid = $this->determineCloudApplication();
    $encrypting_string = $input->getArgument('encrypting_string');

    $client = new GuzzleClient([
      'base_uri' => 'https://api.pipelines.acquia.com',
      'headers' => [
        'X-ACQUIA-PIPELINES-N3-ENDPOINT' => 'https://account.acquia.com',
      ],
    ]);

    $request_params = json_encode([
      'applications' => [$cloudApplicationUuid],
      'data_item' => $encrypting_string,
      'n3_key' => $this->cloudCredentials->getCloudKey(),
      'n3_secret' => $this->cloudCredentials->getCloudSecret(),
    ]);

    try {
      $response = $client->request('POST', '/api/v1/ci/encrypt', [
        'body' => $request_params,
      ]);

      $encrypted_value = $response->getBody()->getContents();

      $this->io->success("Encrypted value of '$encrypting_string'");
      $this->io->writeln($encrypted_value);

      return Command::SUCCESS;
    }
    catch (\Exception $e) {
      $this->io->error($e->getMessage());
      return Command::FAILURE;
    }
  }

}
