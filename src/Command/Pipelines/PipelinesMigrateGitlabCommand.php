<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Pipelines;

use Acquia\Cli\Command\CommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(name: 'pipelines:migrate:gitlab', description: 'Convert an acquia-pipelines.yml file to a generic .gitlab-ci.yml file', aliases: ['p:m:g'])]
final class PipelinesMigrateGitlabCommand extends CommandBase
{
    protected function configure(): void
    {
        $this->addOption('path', null, InputOption::VALUE_REQUIRED, 'Path to the directory containing the acquia-pipelines.yml file. Defaults to the current directory.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sourceFile = $this->resolveSourceFile($input);
        $acquiaPipelinesContents = $this->parseSourceFile($sourceFile['path']);
        $gitlabCiContents = $this->convert($acquiaPipelinesContents);
        $outputPath = Path::join(dirname($sourceFile['path']), '.gitlab-ci.' . $sourceFile['extension']);

        if ($this->localMachineHelper->getFilesystem()->exists($outputPath)) {
            $this->io->warning("Existing $outputPath was overwritten.");
        }

        $this->localMachineHelper->getFilesystem()->dumpFile(
            $outputPath,
            Yaml::dump($gitlabCiContents, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK)
        );

        $this->io->success("Migration complete. Created $outputPath. Review the file before committing — some manual adjustments may be needed.");

        return Command::SUCCESS;
    }

    /**
     * @return array{path: string, extension: string}
     */
    private function resolveSourceFile(InputInterface $input): array
    {
        $dir = $input->getOption('path') ?? $this->projectDir;

        if (!$this->localMachineHelper->getFilesystem()->exists($dir)) {
            throw new AcquiaCliException("The path '{$dir}' does not exist.");
        }

        foreach (['yml', 'yaml'] as $extension) {
            $candidate = Path::join($dir, "acquia-pipelines.$extension");
            if ($this->localMachineHelper->getFilesystem()->exists($candidate)) {
                return ['path' => $candidate, 'extension' => $extension];
            }
        }

        throw new AcquiaCliException("No acquia-pipelines.yml or acquia-pipelines.yaml file found in {$dir}.");
    }

    /**
     * @return array<mixed>
     */
    private function parseSourceFile(string $path): array
    {
        $raw = file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            throw new AcquiaCliException("The file {$path} is empty or unreadable.");
        }
        try {
            $parsed = Yaml::parse($raw);
        } catch (ParseException $e) {
            throw new AcquiaCliException("Failed to parse {$path}: " . $e->getMessage());
        }
        if (!is_array($parsed) || !array_key_exists('events', $parsed)) {
            throw new AcquiaCliException("The file {$path} does not contain an 'events' key.");
        }
        return $parsed;
    }

    /**
     * @param array<mixed> $acquiaPipelinesContents
     * @return array<mixed>
     */
    private function convert(array $acquiaPipelinesContents): array
    {
        $output = [];
        $servicesMeta = $this->migrateServices($acquiaPipelinesContents);
        $variables = $this->migrateVariables($acquiaPipelinesContents);

        if (isset($servicesMeta['image'])) {
            $output['image'] = $servicesMeta['image'];
        }
        if (isset($servicesMeta['services'])) {
            $output['services'] = $servicesMeta['services'];
        }
        if (!empty($variables)) {
            $output['variables'] = $variables;
            $this->io->success("Migrated 'variables' section.");
        }

        // Stages and jobs — populated in Task 5.
        $output['stages'] = ['build'];

        return $output;
    }

    /**
     * @param array<mixed> $contents
     * @return array<mixed>
     */
    private function migrateServices(array $contents): array
    {
        $output = [];

        if (!array_key_exists('services', $contents)) {
            return $output;
        }

        foreach ($contents['services'] as $service) {
            if (is_string($service)) {
                $name = $service;
                $version = null;
            } else {
                $name = (string) array_key_first($service);
                $version = $service[$name]['version'] ?? null;
            }

            match ($name) {
                'php' => $output['image'] = 'php:' . $version,
                'mysql' => $output['services'][] = $version ? "mysql:$version" : 'mysql',
                'composer' => $output['_composer'] = true,
                default => $this->io->warning("Unknown service '$name'. Configure it manually in .gitlab-ci.yml."),
            };
        }

        return $output;
    }

    /**
     * @param array<mixed> $contents
     * @return array<mixed>
     */
    private function migrateVariables(array $contents): array
    {
        if (!array_key_exists('variables', $contents)) {
            return [];
        }

        $vars = $contents['variables'];

        if (array_key_exists('global', $vars) && is_array($vars['global'])) {
            return $vars['global'];
        }

        return $vars;
    }
}
