<?php

namespace App\Command;

use App\Service\SkillCompiler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:compile',
    description: 'Compile all published skills into static API files',
)]
class CompileCommand extends Command
{
    public function __construct(
        private SkillCompiler $compiler,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->compiler->compile();
        $io->success('Skills compiled to public/api/v1/');
        return Command::SUCCESS;
    }
}
