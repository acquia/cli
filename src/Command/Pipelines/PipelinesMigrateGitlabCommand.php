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
    // phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys
    private const EVENT_STAGE_MAP = [
        'build' => [
            'stage' => 'build',
            'when' => null,
            'rules' => null,
        ],
        'fail-on-build' => [
            'stage' => 'fail-on-build',
            'when' => 'on_failure',
            'rules' => null,
        ],
        'post-deploy' => [
            'stage' => 'post-deploy',
            'when' => null,
            'rules' => null,
        ],
        'pr-merged' => [
            'stage' => 'pr-merged',
            'when' => null,
            'rules' => [
                [
                    'if' => '$CI_PIPELINE_SOURCE == "merge_request_event"',
                    'when' => 'on_success',
                ],
            ],
        ],
        'pr-closed' => [
            'stage' => 'pr-closed',
            'when' => null,
            'rules' => [
                [
                    'if' => '$CI_PIPELINE_SOURCE == "merge_request_event"',
                    'when' => 'manual',
                ],
            ],
        ],
    ];
    // phpcs:enable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys

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

        // @infection-ignore-all Depth and indent values are arbitrary defaults; exact output format tested in tests.
        $yamlString = Yaml::dump($gitlabCiContents, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
        $yamlString = $this->injectYamlComments($yamlString, $gitlabCiContents);
        $this->localMachineHelper->getFilesystem()->dumpFile($outputPath, $yamlString);

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
        if (!is_array($parsed['events'])) {
            throw new AcquiaCliException("The file {$path} has an 'events' key but its value is not a mapping.");
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

        $hasComposer = isset($servicesMeta['_composer']) && $servicesMeta['_composer'];
        $eventsMeta = $this->migrateEvents($acquiaPipelinesContents, $hasComposer);

        if (!empty($eventsMeta['stages'])) {
            $output['stages'] = $eventsMeta['stages'];
        }

        foreach ($eventsMeta['jobs'] as $jobName => $jobDef) {
            $output[$jobName] = $jobDef;
        }

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
            // @infection-ignore-all Returning empty array; ArrayOneItem mutant is equivalent here.
            return $output;
        }

        foreach ($contents['services'] as $service) {
            if (is_string($service)) {
                $name = $service;
                $version = null;
            } elseif (is_array($service) && !empty($service)) {
                // @infection-ignore-all (string) cast is defensive; keys are always strings in valid Acquia pipelines YAML.
                $name = (string) array_key_first($service);
                $version = is_array($service[$name]) ? ($service[$name]['version'] ?? null) : null;
            } else {
                $this->io->warning('Skipping malformed service entry. Configure it manually in .gitlab-ci.yml.');
                continue;
            }

            match ($name) {
                'php' => $version !== null
                    ? $output['image'] = 'php:' . $version
                    : $this->io->warning("PHP service has no version specified. Configure 'image:' manually in .gitlab-ci.yml."),
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

    /**
     * @param array<mixed> $contents
     * @return array{stages: list<string>, jobs: array<string, array<mixed>>}
     */
    private function migrateEvents(array $contents, bool $hasComposer): array
    {
        $stages = [];
        $jobs = [];

        foreach (self::EVENT_STAGE_MAP as $eventName => $eventConfig) {
            if (!array_key_exists($eventName, $contents['events'])) {
                continue;
            }

            $eventData = $contents['events'][$eventName];
            if (empty($eventData['steps'])) {
                $this->io->warning("Event '$eventName' has no steps and was skipped.");
                continue;
            }

            $eventHasJob = false;

            foreach ($eventData['steps'] as $step) {
                if (!is_array($step) || empty($step)) {
                    $this->io->warning("Malformed step in event '$eventName'. Skipping.");
                    continue;
                }
                // @infection-ignore-all (string) cast is defensive; step keys are always strings in valid Acquia pipelines YAML.
                $stepName = (string) array_key_first($step);
                $stepData = $step[$stepName];

                if (!is_array($stepData) || empty($stepData['script'])) {
                    $this->io->warning("Step '$stepName' in event '$eventName' has no script. Skipping.");
                    continue;
                }

                $job = [
                    'script' => $stepData['script'],
                    'stage' => $eventConfig['stage'],
                ];

                if ($eventName === 'build' && $hasComposer) {
                    $job['before_script'] = ['composer install'];
                }
                if ($eventConfig['when'] !== null) {
                    $job['when'] = $eventConfig['when'];
                }
                if ($eventConfig['rules'] !== null) {
                    $job['rules'] = $eventConfig['rules'];
                }

                if (array_key_exists($stepName, $jobs)) {
                    $this->io->warning("Step name '$stepName' appears in multiple events. The job from '$eventName' overwrites a previous one.");
                }
                $jobs[$stepName] = $job;
                $eventHasJob = true;
            }

            if ($eventHasJob) {
                $stages[] = $eventConfig['stage'];
                $this->io->success("Migrated '$eventName' event.");
            }
        }

        return ['stages' => $stages, 'jobs' => $jobs];
    }

    /**
     * @param array<mixed> $gitlabContents
     */
    private function injectYamlComments(string $yaml, array $gitlabContents): string
    {
        $commentMap = [
            'pr-closed' => '# TODO: GitLab has no native pipeline trigger for a closed-without-merge MR. This is a best-effort placeholder — review and adjust manually.',
            'pr-merged' => '# TODO: Adjust rule — GitLab has no direct "merged" pipeline event. Consider using push pipelines on your default branch instead.',
        ];

        foreach ($gitlabContents as $jobName => $jobDef) {
            if (!is_array($jobDef) || !isset($jobDef['stage'])) {
                continue;
            }
            $stage = $jobDef['stage'];
            if (!isset($commentMap[$stage])) {
                continue;
            }
            $comment = $commentMap[$stage];
            $yaml = preg_replace(
                '/^(' . preg_quote((string) $jobName, '/') . ':)/m',
                $comment . "\n" . '$1',
                $yaml
            );
        }

        return $yaml;
    }
}
