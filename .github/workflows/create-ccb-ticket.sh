#!/usr/bin/env php
<?php

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use JiraRestApi\Issue\IssueService;
use JiraRestApi\Issue\IssueField;
use JiraRestApi\JiraException;

require_once __DIR__ . '/../../vendor/autoload.php';

$loader = new FilesystemLoader(__DIR__);
$twig = new Environment($loader);
$body = $twig->render('ccb-ticket.twig', [
   // @see https://docs.github.com/en/actions/learn-github-actions/environment-variables#default-environment-variables
  'GITHUB_RELEASE_BODY' => $_ENV['GITHUB_RELEASE_BODY'],
  'GITHUB_RELEASE_NAME' => $_ENV['GITHUB_RELEASE_NAME'],
  'JIRA_HOST' => $_ENV['JIRA_HOST'],
  'GITHUB_ACTIONS_RUN_URL' => $_ENV['GITHUB_ACTIONS_RUN_URL'],
]);

try {
  $issueField = new IssueField();
  $issueField->setProjectKey("CLI")
    ->setSummary($_ENV['GITHUB_RELEASE_NAME'])
    ->setIssueType("Release")
    ->setDescription($body)
    ->addComponents(['Acquia CLI']);

  $issueService = new IssueService();

  $ret = $issueService->create($issueField);
  print "Created $ret->key";
} catch (JiraException $e) {
  print("An orror occurred! " . $e->getMessage());
}