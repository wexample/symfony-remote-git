<?php

declare(strict_types=1);

namespace Wexample\SymfonyRemoteGit\Tests\Unit;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Wexample\PhpRemoteGit\Class\GitRepository;
use Wexample\SymfonyRemoteGit\Entity\RemoteGitRepository;
use Wexample\SymfonyRemoteGit\Service\RemoteGitRefreshService;
use Wexample\SymfonyRemoteGit\Tests\Fixtures\FakeGitHostClient;
use Wexample\SymfonyRemoteGit\Tests\Fixtures\InMemoryRemoteGitRepositoryRepository;

final class RemoteGitRefreshServiceTest extends TestCase
{
    private InMemoryRemoteGitRepositoryRepository $repositories;

    private ArrayAdapter $cache;

    /** @var list<string> */
    private array $warnings = [];

    protected function setUp(): void
    {
        $this->repositories = new InMemoryRemoteGitRepositoryRepository();
        $this->cache = new ArrayAdapter();
    }

    /**
     * @param array<string, FakeGitHostClient> $clients by account key, in the configuration's order
     */
    private function service(array $clients): RemoteGitRefreshService
    {
        $rows = $this->repositories;
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(static function (object $row) use ($rows): void {
            $rows->rows[] = $row;
        });
        $entityManager->method('remove')->willReturnCallback(static function (object $row) use ($rows): void {
            $rows->rows = array_values(array_filter($rows->rows, static fn (object $kept): bool => $kept !== $row));
        });

        $warnings = &$this->warnings;
        $logger = new class ($warnings) extends AbstractLogger {
            public function __construct(private array &$warnings)
            {
            }

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->warnings[] = (string) $message;
            }
        };

        return new RemoteGitRefreshService(
            $this->repositories,
            $entityManager,
            $this->cache,
            $logger,
            24,
            array_map(static fn (FakeGitHostClient $client): array => ['owner' => 'acme', 'client' => $client], $clients),
        );
    }

    private function repository(string $id, string $name): GitRepository
    {
        return new GitRepository($id, $name, 'https://git.test/'.$id);
    }

    /**
     * @return array<string, RemoteGitRepository> by account and name
     */
    private function rows(): array
    {
        $rows = [];

        foreach ($this->repositories->rows as $row) {
            $rows[$row->getAccount().'/'.$row->getName()] = $row;
        }

        ksort($rows);

        return $rows;
    }

    public function testAnAccountIsListedThenEachRepositoryRead(): void
    {
        $github = new FakeGitHostClient(
            [$this->repository('acme/api', 'api'), $this->repository('acme/web', 'web')],
            ['acme/api' => ['2026-11-13' => 4]]
        );

        $this->assertSame(2, $this->service(['github' => $github])->refresh());

        $rows = $this->rows();
        $this->assertSame(['github/api', 'github/web'], array_keys($rows));
        $this->assertSame(['2026-11-13' => 4], $rows['github/api']->getCommits());
        $this->assertNotNull($rows['github/web']->getRefreshedAt());
    }

    public function testAListIsReadOncePerWindowAndARepositoryOnceFresh(): void
    {
        $github = new FakeGitHostClient([$this->repository('acme/api', 'api')]);

        $this->service(['github' => $github])->refresh();
        $this->assertSame(0, $this->service(['github' => $github])->refresh());

        $this->assertSame(1, $github->listed);
        $this->assertSame(['acme/api'], $github->read);
    }

    public function testTheLimitBoundsAPass(): void
    {
        $github = new FakeGitHostClient([$this->repository('acme/a', 'a'), $this->repository('acme/b', 'b'), $this->repository('acme/c', 'c')]);
        $service = $this->service(['github' => $github]);

        $this->assertSame(2, $service->refresh(2));
        $this->assertSame(1, $service->refresh(2));
        $this->assertSame(['acme/a', 'acme/b', 'acme/c'], $github->read);
    }

    public function testAMirrorIsListedButCountedFromTheFirstAccount(): void
    {
        $github = new FakeGitHostClient([$this->repository('acme/api', 'api')]);
        $gitlab = new FakeGitHostClient([$this->repository('42', 'api'), $this->repository('43', 'tools')]);

        $this->service(['github' => $github, 'gitlab' => $gitlab])->refresh();

        $this->assertSame(['github/api', 'gitlab/api', 'gitlab/tools'], array_keys($this->rows()));
        $this->assertSame(['acme/api'], $github->read);
        $this->assertSame(['43'], $gitlab->read);
    }

    public function testARepositoryGoneFromTheHostIsNoLongerCountedAndARenamedOneFollowed(): void
    {
        $github = new FakeGitHostClient([$this->repository('acme/api', 'api'), $this->repository('acme/old', 'old')]);
        $this->service(['github' => $github])->refresh();

        $github->repositories = [$this->repository('acme/api', 'api-v2')];
        $this->cache->clear();
        $this->service(['github' => $github])->refresh();

        $this->assertSame(['github/api-v2'], array_keys($this->rows()));
    }

    public function testCommitsTheHostIsStillCountingAreAskedAgainLater(): void
    {
        $github = new FakeGitHostClient([$this->repository('acme/api', 'api')], ['acme/api' => null]);

        $this->assertSame(0, $this->service(['github' => $github])->refresh());
        $this->assertNull($this->rows()['github/api']->getRefreshedAt());

        $github->commits = ['acme/api' => ['2026-11-13' => 1]];
        $this->assertSame(1, $this->service(['github' => $github])->refresh());
    }

    public function testAFailingHostLeavesWhatWasReadAndIsTriedAgain(): void
    {
        $github = new FakeGitHostClient([$this->repository('acme/api', 'api')], ['acme/api' => ['2026-11-13' => 4]]);
        $this->service(['github' => $github])->refresh();
        $this->cache->clear();
        $this->rows()['github/api']->setCommits(['2026-11-13' => 4], new DateTimeImmutable('-2 days'));

        $github->failing = true;
        $this->assertSame(0, $this->service(['github' => $github])->refresh());

        $this->assertSame(['2026-11-13' => 4], $this->rows()['github/api']->getCommits());
        $this->assertCount(2, $this->warnings);

        // Not marked as listed: the next pass asks the host again.
        $this->service(['github' => $github])->refresh();
        $this->assertSame(3, $github->listed);
    }
}
