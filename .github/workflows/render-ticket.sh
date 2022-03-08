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
  'GITHUB_ACTIONS_RUN_URL' => "{$_ENV['GITHUB_SERVER_URL']}/{$_ENV['GITHUB_REPOSITORY']}/actions/runs/{$_ENV['GITHUB_RUN_ID']}",
]);

try {
  $issueField = new IssueField();
  $issueField->setProjectKey("CLI")
    ->setSummary($_ENV['GITHUB_RELEASE_NAME'])
    //->setPriorityName("Critical")
    ->setIssueType("Release")
    ->setDescription($body)
    //->addVersion([$_ENV['GITHUB_RELEASE_NAME']])
    ->addComponents(['Acquia CLI'])
  ;

  $issueService = new IssueService();

  $ret = $issueService->create($issueField);

  // If success, Returns a link to the created issue.
  var_dump($ret);
} catch (JiraException $e) {
  print("Error Occured! " . $e->getMessage());
}