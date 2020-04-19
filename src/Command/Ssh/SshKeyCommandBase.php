<?php

namespace Acquia\Ads\Command\Ssh;

use Acquia\Ads\Command\CommandBase;
use Acquia\Ads\Exec\ExecTrait;
use AcquiaCloudApi\Endpoints\Ides;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Connector\Connector;
use AcquiaCloudApi\Endpoints\Applications;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Finder\Finder;

/**
 * Class SshKeyCommandBase
 */
abstract class SshKeyCommandBase extends CommandBase
{
    /**
     * @return array|\Symfony\Component\Finder\Finder
     */
    protected function findLocalSshKeys()
    {
        $finder = new Finder();
        $finder->files()->in($this->getApplication()->getLocalMachineHelper()->getHomeDir() . '/.ssh')->name('*.pub');
        $local_keys = iterator_to_array($finder);

        return $local_keys;
    }
}
