<?php

namespace Acquia\Cli\Command\Ide;

use Acquia\Cli\Command\ApiCommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use AcquiaCloudApi\Endpoints\Ides;
use AcquiaCloudApi\Response\IdeResponse;

/**
 * Class IdeCommandBase.
 */
abstract class IdeCommandBase extends ApiCommandBase {

  /**
   * @var string
   */
  private $phpVersionFilePath;

  /**
   * @var string
   */
  private $xdebugIniFilepath;

  const DEFAULT_XDEBUG_INI_FILEPATH = '/home/ide/configs/php/xdebug.ini';

  /**
   * @param string $question_text
   * @param \AcquiaCloudApi\Endpoints\Ides $ides_resource
   *
   * @param $cloud_application_uuid
   *
   * @return \AcquiaCloudApi\Response\IdeResponse
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function promptIdeChoice(
    string $question_text,
    Ides $ides_resource,
    $cloud_application_uuid
  ): ?IdeResponse {
    $ides = iterator_to_array($ides_resource->getAll($cloud_application_uuid));
    if (empty($ides)) {
      throw new AcquiaCliException('No IDEs exist for this application.');
    }

    $choices = [];
    foreach ($ides as $key => $ide) {
      $choices[] = "{$ide->label} ($ide->uuid)";
    }
    $choice = $this->io->choice($question_text, $choices, $choices[0]);
    $chosen_environment_index = array_search($choice, $choices, TRUE);

    return $ides[$chosen_environment_index];
  }

  /**
   * Start service inside IDE.
   *
   * @param string $service
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function startService($service): void {
    $process = $this->localMachineHelper->execute([
      'supervisorctl',
      'start',
      $service,
    ], NULL, NULL, FALSE);
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Unable to start ' . $service . ' in the IDE: {error}', ['error' => $process->getErrorOutput()]);
    }
  }

  /**
   * Stop service inside IDE.
   *
   * @param string $service
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function stopService($service): void {
    $process = $this->localMachineHelper->execute([
      'supervisorctl',
      'stop',
      $service,
    ], NULL, NULL, FALSE);
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Unable to stop ' . $service . ' in the IDE: {error}', ['error' => $process->getErrorOutput()]);
    }
  }

  /**
   * Restart service inside IDE.
   *
   * @param string $service
   *
   * @throws \Acquia\Cli\Exception\AcquiaCliException
   */
  protected function restartService($service): void {
    $process = $this->localMachineHelper->execute([
      'supervisorctl',
      'restart',
      $service,
    ], NULL, NULL, FALSE);
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Unable to restart ' . $service . ' in the IDE: {error}', ['error' => $process->getErrorOutput()]);
    }
  }

  /**
   * @return false|string
   */
  protected function getIdePhpVersion() {
    return $this->localMachineHelper->readFile($this->getIdePhpVersionFilePath());
  }

  /**
   * @param string $file_path
   */
  public function setXdebugIniFilepath(string $file_path): void {
    $this->xdebugIniFilepath = $file_path;
  }

  /**
   *
   * @return string
   */
  public function getXdebugIniFilePath(): string {
    if (!isset($this->xdebugIniFilepath)) {
      $this->xdebugIniFilepath = IdeCommandBase::DEFAULT_XDEBUG_INI_FILEPATH;
    }
    return $this->xdebugIniFilepath;
  }

  /**
   * @param string $php_version
   *   The current php version.
   *
   * @return string
   *   The file path to the xdebug template.
   */
  protected function getXdebugTemplateFilePath(string $php_version): string {
    switch ($php_version) {
      case '7.4':
        return '/home/ide/configs/php/xdebug2.ini';
      default:
        return '/home/ide/configs/php/xdebug3.ini';
    }
  }

  /**
   * @param string $path
   */
  public function setPhpVersionFilePath(string $path): void {
    $this->phpVersionFilePath = $path;
  }

  /**
   * @return string
   */
  public function getIdePhpVersionFilePath(): string {
    if (!isset($this->phpVersionFilePath)) {
      $this->phpVersionFilePath = '/home/ide/configs/php/.version';
    }
    return $this->phpVersionFilePath;
  }

}
