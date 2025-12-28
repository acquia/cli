<?php

declare(strict_types=1);

namespace Acquia\Cli\Command\Ide;

use Acquia\Cli\Command\CommandBase;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'ide:share', description: 'Get the share URL for a Cloud IDE (Added in 1.1.0)')]
final class IdeShareCommand extends CommandBase
{
    /**
     * @var array<mixed>
     */
    private array $shareCodeFilepaths;

    protected function configure(): void
    {
        $this
            ->addOption('regenerate', '', InputOption::VALUE_NONE, 'regenerate the share code')
            ->setHidden(!AcquiaDrupalEnvironmentDetector::isAhIdeEnv());
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->requireCloudIdeEnvironment();

        if ($input->getOption('regenerate')) {
            $this->regenerateShareCode();
        }

        $shareUuid = $this->localMachineHelper->readFile($this->getShareCodeFilepaths()[0]);
        $webUrl = self::getThisCloudIdeWebUrl();

        $this->output->writeln('');
        $this->output->writeln("<comment>Your IDE Share URL:</comment> <href=https://$webUrl>https://$webUrl?share=$shareUuid</>");

        return Command::SUCCESS;
    }

    public function setShareCodeFilepaths(array $filePath): void
    {
        $this->shareCodeFilepaths = $filePath;
    }

    /**
     * @return array<mixed>
     */
    private function getShareCodeFilepaths(): array
    {
        if (!isset($this->shareCodeFilepaths)) {
            $this->shareCodeFilepaths = [
                '/usr/local/share/ide/.sharecode',
                '/home/ide/.sharecode',
            ];
        }
        return $this->shareCodeFilepaths;
    }

    private function regenerateShareCode(): void
    {
        $newShareCode = (string) Uuid::uuid4();
        foreach ($this->getShareCodeFilepaths() as $path) {
            $this->localMachineHelper->writeFile($path, $newShareCode);
        }
    }
}
