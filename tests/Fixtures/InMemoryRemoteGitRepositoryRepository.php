<?php

declare(strict_types=1);

namespace Wexample\SymfonyRemoteGit\Tests\Fixtures;

use Wexample\SymfonyRemoteGit\Entity\RemoteGitRepository;
use Wexample\SymfonyRemoteGit\Repository\RemoteGitRepositoryRepository;

/**
 * The repository table as a list, for the refresh to be read without a
 * database: findBy() is all that findCounted() and the refresh ask of it.
 */
final class InMemoryRemoteGitRepositoryRepository extends RemoteGitRepositoryRepository
{
    /** @var list<RemoteGitRepository> */
    public array $rows = [];

    public function __construct()
    {
    }

    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        $accounts = (array) $criteria['account'];
        $found = array_values(array_filter($this->rows, static fn (RemoteGitRepository $row): bool => in_array($row->getAccount(), $accounts, true)));

        if (isset($orderBy['name'])) {
            usort($found, static fn (RemoteGitRepository $a, RemoteGitRepository $b): int => $a->getName() <=> $b->getName());
        }

        return $found;
    }
}
