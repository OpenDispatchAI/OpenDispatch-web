<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\SkillSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:sync', description: 'Sync skills from the GitHub repository')]
class SyncCommand extends Command
{
    public function __construct(
        private SkillSyncService $syncService,
        private string $skillsRepoUrl,
        private string $skillsRepoDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dir', 'd', InputOption::VALUE_REQUIRED, 'Use a local directory instead of cloning');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $localDir = $input->getOption('dir');

        if ($localDir) {
            $result = $this->syncService->syncFromDirectory($localDir, 'local', '', null);
        } else {
            $result = $this->syncService->sync($this->skillsRepoUrl, $this->skillsRepoDir, 'manual', '', null);
        }

        if ($result->getStatus() === 'success') {
            $io->success("Synced {$result->getSkillCount()} skills.");

            return Command::SUCCESS;
        }

        $io->error($result->getErrorMessage() ?? 'Sync failed');

        return Command::FAILURE;
    }
}
