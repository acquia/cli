#!/usr/bin/env php
<?php

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

require_once __DIR__ . '../../vendor/autoload.php';

$loader = new FilesystemLoader(__DIR__);
$twig = new Environment($loader);
echo $twig->render('ccb-ticket.md', [
  // @see https://docs.github.com/en/actions/learn-github-actions/environment-variables#default-environment-variables
  'GITHUB_RELEASE_BODY' => $_ENV['GITHUB_RELEASE_BODY'],
  'GITHUB_RELEASE_NAME' => $_ENV['GITHUB_RELEASE_NAME'],
  'JIRA_BASE_URL' => $_ENV['JIRA_BASE_URL'],
  'GITHUB_ACTIONS_RUN_URL' => "{$_ENV['GITHUB_SERVER_URL']}/{$_ENV['GITHUB_REPOSITORY']}/actions/runs/{$_ENV['GITHUB_RUN_ID']}",
]);