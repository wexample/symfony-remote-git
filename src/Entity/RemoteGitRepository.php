<?php

namespace Wexample\SymfonyRemoteGit\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyRemoteGit\Repository\RemoteGitRepositoryRepository;

/**
 * A repository an account holds on its host, and the commits of each day of
 * its last year: read again once `refresh_hours` old, kept between two reads
 * — a page counts them without asking the host.
 */
#[ORM\Entity(repositoryClass: RemoteGitRepositoryRepository::class)]
#[ORM\Table(name: 'remote_git_repository')]
#[ORM\UniqueConstraint(name: 'remote_git_repository_key', columns: ['account', 'external_id'])]
class RemoteGitRepository extends AbstractEntity
{
    /** The key of its account in the configuration. */
    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $account;

    /** What its host knows it by (GitRepository::$id). */
    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $externalId;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $name;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $url;

    /** @var array<string, int> commits by `Y-m-d` */
    #[ORM\Column(type: Types::JSON)]
    private array $commits = [];

    /** Null until its commits are first read. */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $refreshedAt = null;

    public function __construct(string $account, string $externalId, string $name, string $url)
    {
        parent::__construct();

        $this->account = $account;
        $this->externalId = $externalId;
        $this->name = $name;
        $this->url = $url;
    }

    public function getAccount(): string
    {
        return $this->account;
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;

        return $this;
    }

    /**
     * @return array<string, int>
     */
    public function getCommits(): array
    {
        return $this->commits;
    }

    /**
     * @param array<string, int> $commits
     */
    public function setCommits(array $commits, DateTimeImmutable $refreshedAt): static
    {
        $this->commits = $commits;
        $this->refreshedAt = $refreshedAt;

        return $this;
    }

    public function getRefreshedAt(): ?DateTimeImmutable
    {
        return $this->refreshedAt;
    }
}
