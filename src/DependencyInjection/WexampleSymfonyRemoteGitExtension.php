<?php

namespace Wexample\SymfonyRemoteGit\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Wexample\PhpRemoteGit\Class\GitHubClient;
use Wexample\PhpRemoteGit\Class\GitLabClient;
use Wexample\SymfonyHelpers\DependencyInjection\AbstractWexampleSymfonyExtension;
use Wexample\SymfonyRemote\DependencyInjection\WexampleSymfonyRemoteExtension;
use Wexample\SymfonyRemoteGit\Service\RemoteGitRefreshService;

class WexampleSymfonyRemoteGitExtension extends AbstractWexampleSymfonyExtension
{
    /** The key of an account's client among symfony-remote's. */
    public const string CLIENT_PREFIX = 'git_';

    private const array CLIENT_CLASSES = [
        Configuration::HOST_GITHUB => GitHubClient::class,
        Configuration::HOST_GITLAB => GitLabClient::class,
    ];

    private const array LABELS = [
        Configuration::HOST_GITHUB => 'GitHub',
        Configuration::HOST_GITLAB => 'GitLab',
    ];

    /**
     * Each account's client is one of symfony-remote's: built from its
     * definition, with its status among the app's remotes.
     */
    public function prepend(ContainerBuilder $container): void
    {
        parent::prepend($container);

        $config = $this->processConfiguration(new Configuration(), $container->getExtensionConfig($this->getAlias()));
        $clients = [];

        foreach ($config['accounts'] as $key => $account) {
            $clients[self::CLIENT_PREFIX.$key] = array_filter([
                'class' => self::CLIENT_CLASSES[$account['host']],
                'label' => self::LABELS[$account['host']].' ('.$key.')',
                'base_url' => $account['base_url'] ?? Configuration::BASE_URLS[$account['host']],
                // Declared only when given: a public account reads without one.
                'api_key' => $account['token'],
                'headers' => Configuration::HOST_GITHUB === $account['host']
                    ? ['Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28']
                    : null,
            ], static fn (mixed $value): bool => null !== $value);
        }

        if ([] !== $clients) {
            $container->prependExtensionConfig('wexample_symfony_remote', ['clients' => $clients]);
        }
    }

    public function load(
        array $configs,
        ContainerBuilder $container
    ): void {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('wexample_symfony_remote_git.refresh_hours', $config['refresh_hours']);
        $container->setParameter('wexample_symfony_remote_git.auto_refresh', $config['auto_refresh']);
        $container->setParameter('wexample_symfony_remote_git.refresh_batch', $config['refresh_batch']);

        $this->loadConfig(
            __DIR__,
            $container
        );

        // In the configuration's order: the first account mirroring a
        // repository is the one it is counted from.
        $accounts = [];

        foreach ($config['accounts'] as $key => $account) {
            $accounts[(string) $key] = [
                'owner' => $account['owner'],
                'client' => new Reference(WexampleSymfonyRemoteExtension::SERVICE_CLIENT_PREFIX.self::CLIENT_PREFIX.$key),
            ];
        }

        $container->getDefinition(RemoteGitRefreshService::class)->setArgument('$accounts', $accounts);
        $container->setParameter('wexample_symfony_remote_git.accounts', array_keys($accounts));
    }
}
