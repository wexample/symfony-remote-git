<?php

declare(strict_types=1);

namespace Wexample\SymfonyRemoteGit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Wexample\PhpRemoteGit\Class\GitHubClient;
use Wexample\PhpRemoteGit\Class\GitLabClient;
use Wexample\SymfonyRemoteGit\DependencyInjection\WexampleSymfonyRemoteGitExtension;

final class WexampleSymfonyRemoteGitExtensionTest extends TestCase
{
    public function testEachAccountIsAClientOfSymfonyRemote(): void
    {
        $container = new ContainerBuilder();
        $container->prependExtensionConfig('wexample_symfony_remote_git', ['accounts' => [
            'main' => ['host' => 'github', 'owner' => 'acme', 'token' => 'secret'],
            'mirror' => ['host' => 'gitlab', 'owner' => 'acme'],
        ]]);

        (new WexampleSymfonyRemoteGitExtension())->prepend($container);

        $this->assertSame([
            'git_main' => [
                'class' => GitHubClient::class,
                'label' => 'GitHub (main)',
                'base_url' => 'https://api.github.com',
                'api_key' => 'secret',
                'api_key_required' => false,
                'headers' => ['Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28'],
            ],
            // No token, no headers: left to symfony-remote's defaults.
            'git_mirror' => [
                'class' => GitLabClient::class,
                'label' => 'GitLab (mirror)',
                'base_url' => 'https://gitlab.com/api/v4',
                'api_key_required' => false,
            ],
        ], $container->getExtensionConfig('wexample_symfony_remote')[0]['clients']);
    }

    public function testWithoutAccountsNoClientIsDeclared(): void
    {
        $container = new ContainerBuilder();

        (new WexampleSymfonyRemoteGitExtension())->prepend($container);

        $this->assertSame([], $container->getExtensionConfig('wexample_symfony_remote'));
    }
}
