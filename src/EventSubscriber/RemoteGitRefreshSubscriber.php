<?php

namespace Wexample\SymfonyRemoteGit\EventSubscriber;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Wexample\SymfonyRemoteGit\Service\RemoteGitRefreshService;

/**
 * Keeps the repositories up to date without a scheduler: after a response
 * has left, `refresh_batch` of them are read again, at most once every few
 * minutes. The pass is marked before it runs, so two workers do not both
 * make it.
 */
class RemoteGitRefreshSubscriber implements EventSubscriberInterface
{
    private const string CACHE_KEY = 'wexample_symfony_remote_git.refreshed';

    /** Seconds between two passes. */
    private const int INTERVAL = 300;

    public function __construct(
        private readonly RemoteGitRefreshService $refresh,
        private readonly CacheItemPoolInterface $cache,
        private readonly bool $autoRefresh,
        private readonly int $refreshBatch,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::TERMINATE => ['onTerminate', -1024]];
    }

    public function onTerminate(TerminateEvent $event): void
    {
        if (! $this->autoRefresh || ! $event->isMainRequest()) {
            return;
        }

        $refreshed = $this->cache->getItem(self::CACHE_KEY);

        if ($refreshed->isHit()) {
            return;
        }

        $this->cache->save($refreshed->set(true)->expiresAfter(self::INTERVAL));
        $this->refresh->refresh($this->refreshBatch);
    }
}
