<?php

namespace Wexample\SymfonyRemoteGit\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public const string HOST_GITHUB = 'github';

    public const string HOST_GITLAB = 'gitlab';

    /** Where each host answers when the account names no instance of its own. */
    public const array BASE_URLS = [
        self::HOST_GITHUB => 'https://api.github.com',
        self::HOST_GITLAB => 'https://gitlab.com/api/v4',
    ];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('wexample_symfony_remote_git');

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('accounts')
                    ->info('The accounts whose repositories are counted, by key, in order: a repository mirrored on two of them is counted once, from the first.')
                    ->useAttributeAsKey('key')
                    ->arrayPrototype()
                        ->children()
                            ->enumNode('host')
                                ->values(array_keys(self::BASE_URLS))
                                ->isRequired()
                            ->end()
                            ->scalarNode('base_url')
                                ->defaultNull()
                                ->info('The API of an instance of its own: `https://gitlab.example.com/api/v4`. The host\'s public one by default.')
                            ->end()
                            ->scalarNode('owner')
                                ->defaultNull()
                                ->info('A GitHub user or organization, a GitLab group. Without one: every repository the token sees, every public one on GitLab without a token either.')
                            ->end()
                            ->scalarNode('token')
                                ->defaultNull()
                                ->info('Without one, GitHub answers sixty requests an hour, too few for an organization; GitLab lists its public projects.')
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->integerNode('refresh_hours')
                    ->info('How old the commits of a repository, and the list of an account, may be before they are read again.')
                    ->defaultValue(24)
                    ->min(1)
                ->end()
                ->booleanNode('auto_refresh')
                    ->info('Reads a few repositories again after a response, at most once every few minutes: no scheduler needed. Off to run remote-git:refresh from one.')
                    ->defaultTrue()
                ->end()
                ->integerNode('refresh_batch')
                    ->info('The repositories read again after one response: the work of a request kept short.')
                    ->defaultValue(3)
                    ->min(1)
                ->end()
            ->end();

        return $treeBuilder;
    }
}
