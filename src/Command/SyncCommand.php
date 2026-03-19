<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\SkillSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:sync', description: 'Sync skills from the GitHub repository')]
class SyncCommand extends Command
{
    public function __construct(
        private readonly SkillSyncService $syncService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $result = $this->syncService->sync('manual', '', null);

        if ($result->getStatus() === 'success') {
            $io->success("Synced {$result->getSkillCount()} skills.");

            return Command::SUCCESS;
        }

        $io->error($result->getErrorMessage() ?? 'Sync failed');

        return Command::FAILURE;
    }
}
