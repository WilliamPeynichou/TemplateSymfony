<?php

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\BaseTeamImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:load-fictive-teams',
    description: 'Charge deux équipes fictives (FC Valdor & AS Méridienne) pour un coach donné',
)]
class LoadFictiveTeamsCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private BaseTeamImporter $baseTeamImporter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Email du coach à qui assigner les équipes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $coach = $this->userRepository->findOneBy(['email' => $input->getArgument('email')]);
        if (!$coach) {
            $io->error('Aucun coach trouvé avec cet email.');
            return Command::FAILURE;
        }

        $valdor = $this->baseTeamImporter->syncFcValdor($coach);
        $io->success(sprintf(
            '%s FC Valdor 2025/2026 pour %s : %d joueur(s) créé(s), %d mis à jour.',
            $valdor['isNewTeam'] ? 'Création de' : 'Mise à jour de',
            $coach->getEmail(),
            $valdor['created'],
            $valdor['updated'],
        ));

        $meridienne = $this->baseTeamImporter->syncAsMeridienne($coach);
        $io->success(sprintf(
            '%s AS Méridienne 2025/2026 pour %s : %d joueur(s) créé(s), %d mis à jour.',
            $meridienne['isNewTeam'] ? 'Création de' : 'Mise à jour de',
            $coach->getEmail(),
            $meridienne['created'],
            $meridienne['updated'],
        ));

        return Command::SUCCESS;
    }
}
