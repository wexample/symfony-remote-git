<?php

namespace Wexample\SymfonyRemoteGit\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Wexample\SymfonyRemoteGit\Service\RemoteGitRefreshService;

#[AsCommand(name: 'remote-git:refresh', description: 'Reads again the repositories of the git accounts whose commits are older than refresh_hours, all of them at once')]
class RemoteGitRefreshCommand extends Command
{
    public function __construct(
        private readonly RemoteGitRefreshService $refresh,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        (new SymfonyStyle($input, $output))->success(sprintf('%d repositories read.', $this->refresh->refresh()));

        return Command::SUCCESS;
    }
}
