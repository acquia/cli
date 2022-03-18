#!/usr/bin/env php
<?php

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
require_once __DIR__ . '/../../vendor/autoload.php';

$loader = new FilesystemLoader(__DIR__);
$twig = new Environment($loader);
$body = $twig->render('ccb-ticket.twig', [
   // @see https://docs.github.com/en/actions/learn-github-actions/environment-variables#default-environment-variables
  'GITHUB_RELEASE_BODY' => $argv[1],
  'GITHUB_RELEASE_NAME' => $argv[2],
  'GITHUB_ACTIONS_RUN_URL' => $argv[3],
  'JIRA_BASE_URL' => $argv[4],
]);
echo $body;