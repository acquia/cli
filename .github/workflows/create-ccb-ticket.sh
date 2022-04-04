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
  'GITHUB_RELEASE_URL' => $argv[3],
  'GITHUB_ACTIONS_RUN_URL' => $argv[4],
  'JIRA_BASE_URL' => $argv[5],
]);
$body = htmlspecialchars($body);
$body = preg_replace(
  '/[\x{1F600}-\x{1F64F}\x{2700}-\x{27BF}\x{1F680}-\x{1F6FF}\x{24C2}-\x{1F251}\x{1F30D}-\x{1F567}\x{1F900}-\x{1F9FF}\x{1F300}-\x{1F5FF}]/u',
  '[emoji-removed]',
  $body
);
echo $body;