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
    name: 'app:load-psg',
    description: 'Charge l\'effectif Paris Saint-Germain 2025/2026 pour un coach donné',
)]
class LoadPsgFixtureCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private BaseTeamImporter $baseTeamImporter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Email du coach à qui assigner l\'équipe');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $coach = $this->userRepository->findOneBy(['email' => $input->getArgument('email')]);
        if (!$coach) {
            $io->error('Aucun coach trouvé avec cet email.');
            return Command::FAILURE;
        }

        $result = $this->baseTeamImporter->syncPsg($coach);

        $io->success(sprintf(
            '%s Paris Saint-Germain 2025/2026 pour %s : %d joueur(s) créé(s), %d mis à jour, %d supprimé(s)%s.',
            $result['isNewTeam'] ? 'Création de l\'équipe' : 'Mise à jour de l\'équipe',
            $coach->getEmail(),
            $result['created'],
            $result['updated'],
            $result['removed'],
            $result['kept'] > 0 ? sprintf(', %d conservé(s) car référencé(s)', $result['kept']) : ''
        ));
        return Command::SUCCESS;
    }
}
