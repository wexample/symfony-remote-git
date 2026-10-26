<?php

namespace Wexample\SymfonyRemoteGit\Repository;

use Wexample\SymfonyHelpers\Repository\AbstractRepository;
use Wexample\SymfonyRemoteGit\Entity\RemoteGitRepository;

/**
 * @method RemoteGitRepository saveNewRemoteGitRepository(string $account, string $externalId, string $name, string $url)
 */
class RemoteGitRepositoryRepository extends AbstractRepository
{
    public static function getEntityClassName(): string
    {
        return RemoteGitRepository::class;
    }

    public function createNewRemoteGitRepository(string $account, string $externalId, string $name, string $url): RemoteGitRepository
    {
        return new RemoteGitRepository($account, $externalId, $name, $url);
    }

    /**
     * The repositories of the accounts, in their order, each account's by
     * name.
     *
     * @param list<string> $accounts
     *
     * @return list<RemoteGitRepository>
     */
    public function findOfAccounts(array $accounts): array
    {
        $repositories = $this->findBy(['account' => $accounts], ['name' => self::SORT_ASC]);
        $rank = array_flip($accounts);
        usort($repositories, static fn (RemoteGitRepository $a, RemoteGitRepository $b): int => $rank[$a->getAccount()] <=> $rank[$b->getAccount()]);

        return $repositories;
    }

    /**
     * The repositories each counted once: the first account holding a name
     * keeps it, a mirror on a later one is left out.
     *
     * @param list<string> $accounts
     *
     * @return list<RemoteGitRepository>
     */
    public function findCounted(array $accounts): array
    {
        $counted = [];

        foreach ($this->findOfAccounts($accounts) as $repository) {
            $counted[$repository->getName()] ??= $repository;
        }

        return array_values($counted);
    }
}
