<?php

declare(strict_types=1);

namespace Wexample\SymfonyRemoteGit\Tests\Fixtures;

use DateTimeImmutable;
use Wexample\PhpApi\Exceptions\ApiException;
use Wexample\PhpRemoteGit\Class\GitRepository;
use Wexample\PhpRemoteGit\Interface\GitHostClientInterface;

final class FakeGitHostClient implements GitHostClientInterface
{
    public int $listed = 0;

    /** @var list<string> the ids whose commits were asked */
    public array $read = [];

    /**
     * @param list<GitRepository>                  $repositories
     * @param array<string, array<string, int>|null> $commits by repository id; null while the host counts them
     */
    public function __construct(
        public array $repositories = [],
        public array $commits = [],
        public bool $failing = false,
    ) {
    }

    public function getRepositories(?string $owner): array
    {
        ++$this->listed;

        if ($this->failing) {
            throw new ApiException('Host unreachable');
        }

        return $this->repositories;
    }

    public function getDailyCommits(GitRepository $repository, DateTimeImmutable $since): ?array
    {
        $this->read[] = $repository->id;

        if ($this->failing) {
            throw new ApiException('Host unreachable');
        }

        return array_key_exists($repository->id, $this->commits) ? $this->commits[$repository->id] : [];
    }
}
