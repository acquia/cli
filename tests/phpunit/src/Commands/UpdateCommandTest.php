<?php

namespace Acquia\Cli\Tests\Commands;

use Acquia\Cli\Command\UpdateCommand;
use Acquia\Cli\Tests\CommandTestBase;
use drupol\phposinfo\OsInfo;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Prophecy\Argument;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Process\Process;
use Webmozart\PathUtil\Path;

/**
 * Class UpdateCommandTest.
 *
 * @property \Acquia\Cli\Command\UpdateCommand $command
 * @package Acquia\Cli\Tests\Commands
 */
class UpdateCommandTest extends CommandTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createCommand(): Command {
    return $this->injectCommand(UpdateCommand::class);
  }

  public function testNonPharException(): void {

    try {
      $this->executeCommand([], []);
    }
    catch (Exception $e) {
      $this->assertStringContainsString('update only works when running the phar version of ', $e->getMessage());
    }
  }

  /**
   * @dataProvider providerTestDownloadUpdate
   * @requires OS linux|darwin
   * @throws \Exception
   */
  public function testDownloadUpdate($releases): void {

    $stub_phar = $this->fs->tempnam(sys_get_temp_dir(), 'acli_phar');
    $this->fs->chmod($stub_phar, 0751);
    $original_file_perms = fileperms($stub_phar);
    $this->command->setPharPath($stub_phar);
    $guzzle_client = $this->mockGuzzleClient($releases);
    $this->command->setClient($guzzle_client->reveal());

    $args = [
      '--allow-unstable' => '',
    ];
    $this->executeCommand($args, []);
    $output = $this->getDisplay();
    $this->assertEquals(0, $this->getStatusCode());
    $this->assertStringContainsString('Updated from UNKNOWN to v1.0.0-beta3', $output);
    $this->assertFileExists($stub_phar);

    // The file permissions on the new phar should be the same as on the old phar.
    $this->assertEquals($original_file_perms, fileperms($stub_phar) );
  }

  /**
   * @param $releases
   *
   * @return \Prophecy\Prophecy\ObjectProphecy
   */
  protected function mockGuzzleClient($releases): \Prophecy\Prophecy\ObjectProphecy {
    $stream = $this->prophet->prophesize(StreamInterface::class);
    $stream->getContents()->willReturn(json_encode($releases));
    $response = $this->prophet->prophesize(Response::class);
    $response->getBody()->willReturn($stream->reveal());
    $guzzle_client = $this->prophet->prophesize(Client::class);
    $guzzle_client->request('GET', Argument::containingString('https://api.github.com/repos'), Argument::type('array'))
      ->willReturn($response->reveal());

    $stream = $this->prophet->prophesize(StreamInterface::class);
    $phar_contents = file_get_contents(Path::join($this->fixtureDir, 'test.phar'));
    $stream->getContents()->willReturn($phar_contents);
    $response = $this->prophet->prophesize(Response::class);
    $response->getBody()->willReturn($stream->reveal());
    $guzzle_client->request('GET', 'https://github.com/acquia/cli/releases/download/v1.0.0-beta3/acli.phar', Argument::type('array'))
      ->willReturn($response->reveal());

    return $guzzle_client;
  }

  /**
   * @return string
   */
  protected function createPharStub(): string {
    $stub_phar = $this->fs->tempnam(sys_get_temp_dir(), 'acli_phar');
    $this->fs->chmod($stub_phar, 0751);
    $this->command->setPharPath($stub_phar);
    return $stub_phar;
  }

  public function providerTestDownloadUpdate(): array {
    $response = [
      0 =>
        [
          'url' => 'https://api.github.com/repos/acquia/cli/releases/27415350',
          'assets_url' => 'https://api.github.com/repos/acquia/cli/releases/27415350/assets',
          'upload_url' => 'https://uploads.github.com/repos/acquia/cli/releases/27415350/assets{?name,label}',
          'html_url' => 'https://github.com/acquia/cli/releases/tag/v1.0.0-beta4',
          'id' => 27415350,
          'node_id' => 'MDc6UmVsZWFzZTI3NDE1MzUw',
          'tag_name' => 'v1.0.0-beta4',
          'target_commitish' => 'master',
          'name' => 'v1.0.0-beta4',
          'draft' => FALSE,
          'author' =>
            [
              'login' => 'grasmash',
              'id' => 539205,
              'node_id' => 'MDQ6VXNlcjUzOTIwNQ==',
              'avatar_url' => 'https://avatars0.githubusercontent.com/u/539205?v=4',
              'gravatar_id' => '',
              'url' => 'https://api.github.com/users/grasmash',
              'html_url' => 'https://github.com/grasmash',
              'followers_url' => 'https://api.github.com/users/grasmash/followers',
              'following_url' => 'https://api.github.com/users/grasmash/following{/other_user}',
              'gists_url' => 'https://api.github.com/users/grasmash/gists{/gist_id}',
              'starred_url' => 'https://api.github.com/users/grasmash/starred{/owner}{/repo}',
              'subscriptions_url' => 'https://api.github.com/users/grasmash/subscriptions',
              'organizations_url' => 'https://api.github.com/users/grasmash/orgs',
              'repos_url' => 'https://api.github.com/users/grasmash/repos',
              'events_url' => 'https://api.github.com/users/grasmash/events{/privacy}',
              'received_events_url' => 'https://api.github.com/users/grasmash/received_events',
              'type' => 'User',
              'site_admin' => FALSE,
            ],
          'prerelease' => TRUE,
          'created_at' => '2020-06-10T01:40:15Z',
          'published_at' => '2020-06-10T14:48:22Z',
          'assets' => [],
          'tarball_url' => 'https://api.github.com/repos/acquia/cli/tarball/v1.0.0-beta4',
          'zipball_url' => 'https://api.github.com/repos/acquia/cli/zipball/v1.0.0-beta4',
          'body' => '- Prevent updating to releases with missing phars. (#136)
- Correcting usage example for api:* option with array value. (#138)
- Correctly set Phar path for self update. (#137)
',
        ],
      1 =>
        [
          'url' => 'https://api.github.com/repos/acquia/cli/releases/27387040',
          'assets_url' => 'https://api.github.com/repos/acquia/cli/releases/27387040/assets',
          'upload_url' => 'https://uploads.github.com/repos/acquia/cli/releases/27387040/assets{?name,label}',
          'html_url' => 'https://github.com/acquia/cli/releases/tag/v1.0.0-beta3',
          'id' => 27387040,
          'node_id' => 'MDc6UmVsZWFzZTI3Mzg3MDQw',
          'tag_name' => 'v1.0.0-beta3',
          'target_commitish' => 'master',
          'name' => 'v1.0.0-beta3',
          'draft' => FALSE,
          'author' =>
            [
              'login' => 'grasmash',
              'id' => 539205,
              'node_id' => 'MDQ6VXNlcjUzOTIwNQ==',
              'avatar_url' => 'https://avatars0.githubusercontent.com/u/539205?v=4',
              'gravatar_id' => '',
              'url' => 'https://api.github.com/users/grasmash',
              'html_url' => 'https://github.com/grasmash',
              'followers_url' => 'https://api.github.com/users/grasmash/followers',
              'following_url' => 'https://api.github.com/users/grasmash/following{/other_user}',
              'gists_url' => 'https://api.github.com/users/grasmash/gists{/gist_id}',
              'starred_url' => 'https://api.github.com/users/grasmash/starred{/owner}{/repo}',
              'subscriptions_url' => 'https://api.github.com/users/grasmash/subscriptions',
              'organizations_url' => 'https://api.github.com/users/grasmash/orgs',
              'repos_url' => 'https://api.github.com/users/grasmash/repos',
              'events_url' => 'https://api.github.com/users/grasmash/events{/privacy}',
              'received_events_url' => 'https://api.github.com/users/grasmash/received_events',
              'type' => 'User',
              'site_admin' => FALSE,
            ],
          'prerelease' => TRUE,
          'created_at' => '2020-06-09T20:48:56Z',
          'published_at' => '2020-06-09T20:54:10Z',
          'assets' =>
            [
              0 =>
                [
                  'url' => 'https://api.github.com/repos/acquia/cli/releases/assets/21591990',
                  'id' => 21591990,
                  'node_id' => 'MDEyOlJlbGVhc2VBc3NldDIxNTkxOTkw',
                  'name' => 'acli.phar',
                  'label' => NULL,
                  'uploader' =>
                    [
                      'login' => 'grasmash',
                      'id' => 539205,
                      'node_id' => 'MDQ6VXNlcjUzOTIwNQ==',
                      'avatar_url' => 'https://avatars0.githubusercontent.com/u/539205?v=4',
                      'gravatar_id' => '',
                      'url' => 'https://api.github.com/users/grasmash',
                      'html_url' => 'https://github.com/grasmash',
                      'followers_url' => 'https://api.github.com/users/grasmash/followers',
                      'following_url' => 'https://api.github.com/users/grasmash/following{/other_user}',
                      'gists_url' => 'https://api.github.com/users/grasmash/gists{/gist_id}',
                      'starred_url' => 'https://api.github.com/users/grasmash/starred{/owner}{/repo}',
                      'subscriptions_url' => 'https://api.github.com/users/grasmash/subscriptions',
                      'organizations_url' => 'https://api.github.com/users/grasmash/orgs',
                      'repos_url' => 'https://api.github.com/users/grasmash/repos',
                      'events_url' => 'https://api.github.com/users/grasmash/events{/privacy}',
                      'received_events_url' => 'https://api.github.com/users/grasmash/received_events',
                      'type' => 'User',
                      'site_admin' => FALSE,
                    ],
                  'content_type' => 'application/octet-stream',
                  'state' => 'uploaded',
                  'size' => 9158519,
                  'download_count' => 27,
                  'created_at' => '2020-06-09T21:13:34Z',
                  'updated_at' => '2020-06-09T21:13:37Z',
                  'browser_download_url' => 'https://github.com/acquia/cli/releases/download/v1.0.0-beta3/acli.phar',
                ],
            ],
          'tarball_url' => 'https://api.github.com/repos/acquia/cli/tarball/v1.0.0-beta3',
          'zipball_url' => 'https://api.github.com/repos/acquia/cli/zipball/v1.0.0-beta3',
          'body' => '- Add git clone scenario to refresh command. (#107)
- Fixes #121: Ship required .sh file with phar. (#122) …
- Removing command cache. (#125) …
- Reduce phar size with compactors (#127)
- Fixes #120: Broken parameters for api:* commands. (#126)
- Fixes #123: Infer applicationUuid argument for api:* commands. (#128)
- Check blt.yml for Cloud app uuid. (#130)
- Fixes #132: Allowing multiple arguments for remote:drush command. (#133)',
        ],
      2 =>
        [
          'url' => 'https://api.github.com/repos/acquia/cli/releases/27281238',
          'assets_url' => 'https://api.github.com/repos/acquia/cli/releases/27281238/assets',
          'upload_url' => 'https://uploads.github.com/repos/acquia/cli/releases/27281238/assets{?name,label}',
          'html_url' => 'https://github.com/acquia/cli/releases/tag/v1.0.0-beta2',
          'id' => 27281238,
          'node_id' => 'MDc6UmVsZWFzZTI3MjgxMjM4',
          'tag_name' => 'v1.0.0-beta2',
          'target_commitish' => '244668f023ec5b95c3ed403e5b43b397faaa2d12',
          'name' => 'v1.0.0-beta2',
          'draft' => FALSE,
          'author' =>
            [
              'login' => 'danepowell',
              'id' => 1984514,
              'node_id' => 'MDQ6VXNlcjE5ODQ1MTQ=',
              'avatar_url' => 'https://avatars1.githubusercontent.com/u/1984514?v=4',
              'gravatar_id' => '',
              'url' => 'https://api.github.com/users/danepowell',
              'html_url' => 'https://github.com/danepowell',
              'followers_url' => 'https://api.github.com/users/danepowell/followers',
              'following_url' => 'https://api.github.com/users/danepowell/following{/other_user}',
              'gists_url' => 'https://api.github.com/users/danepowell/gists{/gist_id}',
              'starred_url' => 'https://api.github.com/users/danepowell/starred{/owner}{/repo}',
              'subscriptions_url' => 'https://api.github.com/users/danepowell/subscriptions',
              'organizations_url' => 'https://api.github.com/users/danepowell/orgs',
              'repos_url' => 'https://api.github.com/users/danepowell/repos',
              'events_url' => 'https://api.github.com/users/danepowell/events{/privacy}',
              'received_events_url' => 'https://api.github.com/users/danepowell/received_events',
              'type' => 'User',
              'site_admin' => FALSE,
            ],
          'prerelease' => TRUE,
          'created_at' => '2020-06-05T22:54:32Z',
          'published_at' => '2020-06-05T22:57:52Z',
          'assets' =>
            [
              0 =>
                [
                  'url' => 'https://api.github.com/repos/acquia/cli/releases/assets/21460998',
                  'id' => 21460998,
                  'node_id' => 'MDEyOlJlbGVhc2VBc3NldDIxNDYwOTk4',
                  'name' => 'acli.phar',
                  'label' => '',
                  'uploader' =>
                    [
                      'login' => 'acquia-cli-deploy',
                      'id' => 66086891,
                      'node_id' => 'MDQ6VXNlcjY2MDg2ODkx',
                      'avatar_url' => 'https://avatars3.githubusercontent.com/u/66086891?v=4',
                      'gravatar_id' => '',
                      'url' => 'https://api.github.com/users/acquia-cli-deploy',
                      'html_url' => 'https://github.com/acquia-cli-deploy',
                      'followers_url' => 'https://api.github.com/users/acquia-cli-deploy/followers',
                      'following_url' => 'https://api.github.com/users/acquia-cli-deploy/following{/other_user}',
                      'gists_url' => 'https://api.github.com/users/acquia-cli-deploy/gists{/gist_id}',
                      'starred_url' => 'https://api.github.com/users/acquia-cli-deploy/starred{/owner}{/repo}',
                      'subscriptions_url' => 'https://api.github.com/users/acquia-cli-deploy/subscriptions',
                      'organizations_url' => 'https://api.github.com/users/acquia-cli-deploy/orgs',
                      'repos_url' => 'https://api.github.com/users/acquia-cli-deploy/repos',
                      'events_url' => 'https://api.github.com/users/acquia-cli-deploy/events{/privacy}',
                      'received_events_url' => 'https://api.github.com/users/acquia-cli-deploy/received_events',
                      'type' => 'User',
                      'site_admin' => FALSE,
                    ],
                  'content_type' => 'application/octet-stream',
                  'state' => 'uploaded',
                  'size' => 9815202,
                  'download_count' => 76,
                  'created_at' => '2020-06-05T23:01:59Z',
                  'updated_at' => '2020-06-05T23:02:00Z',
                  'browser_download_url' => 'https://github.com/acquia/cli/releases/download/v1.0.0-beta2/acli.phar',
                ],
            ],
          'tarball_url' => 'https://api.github.com/repos/acquia/cli/tarball/v1.0.0-beta2',
          'zipball_url' => 'https://api.github.com/repos/acquia/cli/zipball/v1.0.0-beta2',
          'body' => '- Fix self-update command (#89)
- Check if machine is already authenticated for auth:login (#100)
- Fixes #96: Remove api:accounts:drush-aliases command. #97
- Allowing Cloud app ID to be passed to ide:* commands. (#102)
- Adding cloud-env-uuid to log tail command. (#105)
- Check if repository is already linked in link command. (#101)
- Fixes #110: api:environments:domains-clear-varnish not working. (#115)',
        ],
      3 =>
        [
          'url' => 'https://api.github.com/repos/acquia/cli/releases/27104247',
          'assets_url' => 'https://api.github.com/repos/acquia/cli/releases/27104247/assets',
          'upload_url' => 'https://uploads.github.com/repos/acquia/cli/releases/27104247/assets{?name,label}',
          'html_url' => 'https://github.com/acquia/cli/releases/tag/v1.0.0-beta1',
          'id' => 27104247,
          'node_id' => 'MDc6UmVsZWFzZTI3MTA0MjQ3',
          'tag_name' => 'v1.0.0-beta1',
          'target_commitish' => 'f291b8401530d8c65810ccc758ea09262778ecbd',
          'name' => 'v1.0.0-beta1',
          'draft' => FALSE,
          'author' =>
            [
              'login' => 'danepowell',
              'id' => 1984514,
              'node_id' => 'MDQ6VXNlcjE5ODQ1MTQ=',
              'avatar_url' => 'https://avatars1.githubusercontent.com/u/1984514?v=4',
              'gravatar_id' => '',
              'url' => 'https://api.github.com/users/danepowell',
              'html_url' => 'https://github.com/danepowell',
              'followers_url' => 'https://api.github.com/users/danepowell/followers',
              'following_url' => 'https://api.github.com/users/danepowell/following{/other_user}',
              'gists_url' => 'https://api.github.com/users/danepowell/gists{/gist_id}',
              'starred_url' => 'https://api.github.com/users/danepowell/starred{/owner}{/repo}',
              'subscriptions_url' => 'https://api.github.com/users/danepowell/subscriptions',
              'organizations_url' => 'https://api.github.com/users/danepowell/orgs',
              'repos_url' => 'https://api.github.com/users/danepowell/repos',
              'events_url' => 'https://api.github.com/users/danepowell/events{/privacy}',
              'received_events_url' => 'https://api.github.com/users/danepowell/received_events',
              'type' => 'User',
              'site_admin' => FALSE,
            ],
          'prerelease' => TRUE,
          'created_at' => '2020-06-01T17:09:57Z',
          'published_at' => '2020-06-01T17:14:25Z',
          'assets' =>
            [
              0 =>
                [
                  'url' => 'https://api.github.com/repos/acquia/cli/releases/assets/21258604',
                  'id' => 21258604,
                  'node_id' => 'MDEyOlJlbGVhc2VBc3NldDIxMjU4NjA0',
                  'name' => 'acli.phar',
                  'label' => '',
                  'uploader' =>
                    [
                      'login' => 'acquia-cli-deploy',
                      'id' => 66086891,
                      'node_id' => 'MDQ6VXNlcjY2MDg2ODkx',
                      'avatar_url' => 'https://avatars3.githubusercontent.com/u/66086891?v=4',
                      'gravatar_id' => '',
                      'url' => 'https://api.github.com/users/acquia-cli-deploy',
                      'html_url' => 'https://github.com/acquia-cli-deploy',
                      'followers_url' => 'https://api.github.com/users/acquia-cli-deploy/followers',
                      'following_url' => 'https://api.github.com/users/acquia-cli-deploy/following{/other_user}',
                      'gists_url' => 'https://api.github.com/users/acquia-cli-deploy/gists{/gist_id}',
                      'starred_url' => 'https://api.github.com/users/acquia-cli-deploy/starred{/owner}{/repo}',
                      'subscriptions_url' => 'https://api.github.com/users/acquia-cli-deploy/subscriptions',
                      'organizations_url' => 'https://api.github.com/users/acquia-cli-deploy/orgs',
                      'repos_url' => 'https://api.github.com/users/acquia-cli-deploy/repos',
                      'events_url' => 'https://api.github.com/users/acquia-cli-deploy/events{/privacy}',
                      'received_events_url' => 'https://api.github.com/users/acquia-cli-deploy/received_events',
                      'type' => 'User',
                      'site_admin' => FALSE,
                    ],
                  'content_type' => 'application/octet-stream',
                  'state' => 'uploaded',
                  'size' => 7010058,
                  'download_count' => 268,
                  'created_at' => '2020-06-01T17:19:28Z',
                  'updated_at' => '2020-06-01T17:19:29Z',
                  'browser_download_url' => 'https://github.com/acquia/cli/releases/download/v1.0.0-beta1/acli.phar',
                ],
            ],
          'tarball_url' => 'https://api.github.com/repos/acquia/cli/tarball/v1.0.0-beta1',
          'zipball_url' => 'https://api.github.com/repos/acquia/cli/zipball/v1.0.0-beta1',
          'body' => 'Initial release.',
        ],
    ];

    return [
      [$response],
    ];
  }

}
