<?php

declare(strict_types=1);

namespace App\Command;

use App\Message\WeeklyInsightMessage;
use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * À planifier en cron hebdomadaire (lundi 8h par ex.) :
 *
 *   0 8 * * 1 docker compose exec -T php bin/console app:weekly-insight
 */
#[AsCommand(
    name: 'app:weekly-insight',
    description: 'Dispatch un WeeklyInsightMessage pour chaque coach actif.',
)]
final class WeeklyInsightDispatchCommand extends Command
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $count = 0;
        foreach ($this->users->findAll() as $u) {
            $this->bus->dispatch(new WeeklyInsightMessage((int) $u->getId()));
            ++$count;
        }
        $io->success(sprintf('%d briefings dispatchés.', $count));

        return Command::SUCCESS;
    }
}
