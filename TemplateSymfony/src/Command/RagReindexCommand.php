<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\MatchNoteRepository;
use App\Service\AgentRagIndexer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:rag:reindex',
    description: 'Réindexe toutes les notes de match dans la base vectorielle de l\'agent.',
)]
final class RagReindexCommand extends Command
{
    public function __construct(
        private readonly MatchNoteRepository $notes,
        private readonly AgentRagIndexer $indexer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $all = $this->notes->findAll();

        $io->progressStart(\count($all));
        $errors = 0;
        foreach ($all as $note) {
            try {
                $this->indexer->indexMatchNote($note);
            } catch (\Throwable $e) {
                ++$errors;
                $io->warning(sprintf('Note #%d: %s', $note->getId() ?? 0, $e->getMessage()));
            }
            $io->progressAdvance();
        }
        $io->progressFinish();

        if ($errors > 0) {
            $io->warning(sprintf('%d erreur(s) pendant l\'indexation.', $errors));

            return Command::FAILURE;
        }

        $io->success(sprintf('%d note(s) indexée(s) dans la base vectorielle.', \count($all)));

        return Command::SUCCESS;
    }
}
