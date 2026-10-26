<?php

namespace Wexample\SymfonyRemoteGit\Service;

use DateTimeImmutable;
use Wexample\SymfonyGit\Interface\GitActivityProviderInterface;
use Wexample\SymfonyRemoteGit\Entity\RemoteGitRepository;
use Wexample\SymfonyRemoteGit\Repository\RemoteGitRepositoryRepository;

/**
 * The repositories of the configured accounts as read last, each counted
 * once: what symfony-git sums up. Nothing is asked of a host here.
 */
class RemoteGitActivityProvider implements GitActivityProviderInterface
{
    /**
     * @param list<string> $accounts the keys of the accounts, in order
     */
    public function __construct(
        private readonly RemoteGitRepositoryRepository $repositories,
        private readonly array $accounts = [],
    ) {
    }

    public function getRepositoryNames(): array
    {
        return array_map(
            static fn (RemoteGitRepository $repository): string => $repository->getName(),
            $this->repositories->findCounted($this->accounts)
        );
    }

    public function getDailyCommits(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $first = $from->format('Y-m-d');
        $last = $to->format('Y-m-d');
        $days = [];

        foreach ($this->repositories->findCounted($this->accounts) as $repository) {
            foreach ($repository->getCommits() as $day => $commits) {
                if ($day >= $first && $day < $last) {
                    $days[$day] = ($days[$day] ?? 0) + $commits;
                }
            }
        }

        return $days;
    }
}
