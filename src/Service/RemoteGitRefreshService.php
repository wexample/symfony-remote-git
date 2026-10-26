<?php

namespace Wexample\SymfonyRemoteGit\Service;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Wexample\PhpApi\Exceptions\ApiException;
use Wexample\PhpRemoteGit\Class\GitRepository;
use Wexample\PhpRemoteGit\Interface\GitHostClientInterface;
use Wexample\SymfonyGit\Service\GitActivityService;
use Wexample\SymfonyRemoteGit\Entity\RemoteGitRepository;
use Wexample\SymfonyRemoteGit\Repository\RemoteGitRepositoryRepository;

/**
 * Reads the hosts again, a little at a time: an account's list once it is
 * `refresh_hours` old, then the commits of its oldest repositories. A mirror
 * of a repository an earlier account holds is listed, never read: counted
 * once, from the first.
 *
 * A host failing leaves what was read before, logged: tried again on the
 * next pass.
 */
class RemoteGitRefreshService
{
    private const string LISTED_KEY = 'wexample_symfony_remote_git.listed.';

    /**
     * @param array<string, array{owner: ?string, client: GitHostClientInterface}> $accounts
     *                                                                                    by key, in the configuration's order
     */
    public function __construct(
        private readonly RemoteGitRepositoryRepository $repositories,
        private readonly EntityManagerInterface $entityManager,
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly int $refreshHours,
        private readonly array $accounts = [],
    ) {
    }

    /**
     * @return int the repositories whose commits were read
     */
    public function refresh(int $limit = PHP_INT_MAX): int
    {
        $now = new DateTimeImmutable();
        $stale = $now->modify('-'.$this->refreshHours.' hours');

        foreach ($this->accounts as $key => $account) {
            $this->listIfStale($key, $account, $now);
        }

        $read = 0;

        foreach ($this->repositories->findCounted(array_keys($this->accounts)) as $repository) {
            if ($read >= $limit) {
                break;
            }

            if ($repository->getRefreshedAt() > $stale) {
                continue;
            }

            if ($this->read($repository, $now)) {
                ++$read;
            }
        }

        $this->entityManager->flush();

        return $read;
    }

    /**
     * @param array{owner: ?string, client: GitHostClientInterface} $account
     */
    private function listIfStale(string $key, array $account, DateTimeImmutable $now): void
    {
        $listed = $this->cache->getItem(self::LISTED_KEY.$key);

        if ($listed->isHit()) {
            return;
        }

        try {
            $found = $account['client']->getRepositories($account['owner']);
        } catch (ApiException $exception) {
            $this->logger->warning('The repositories of the git account {account} could not be listed: {message}', ['account' => $key, 'message' => $exception->getMessage()]);

            return;
        }

        $known = [];

        foreach ($this->repositories->findBy(['account' => $key]) as $repository) {
            $known[$repository->getExternalId()] = $repository;
        }

        foreach ($found as $repository) {
            if (isset($known[$repository->id])) {
                $known[$repository->id]->setName($repository->name)->setUrl($repository->url);
                unset($known[$repository->id]);
            } else {
                $this->entityManager->persist($this->repositories->createNewRemoteGitRepository($key, $repository->id, $repository->name, $repository->url));
            }
        }

        // Gone from the host: no longer counted.
        foreach ($known as $repository) {
            $this->entityManager->remove($repository);
        }

        $this->entityManager->flush();
        $this->cache->save($listed->set($now->getTimestamp())->expiresAfter($this->refreshHours * 3600));
    }

    /**
     * @return bool whether its commits could be read: GitHub may still be
     *              counting them
     */
    private function read(RemoteGitRepository $repository, DateTimeImmutable $now): bool
    {
        $client = $this->accounts[$repository->getAccount()]['client'];

        try {
            $commits = $client->getDailyCommits(
                new GitRepository($repository->getExternalId(), $repository->getName(), $repository->getUrl()),
                $now->modify('-'.GitActivityService::DAYS.' days')
            );
        } catch (ApiException $exception) {
            $this->logger->warning('The commits of {repository} could not be read: {message}', ['repository' => $repository->getUrl(), 'message' => $exception->getMessage()]);

            return false;
        }

        if (null === $commits) {
            return false;
        }

        $repository->setCommits($commits, $now);

        return true;
    }
}
