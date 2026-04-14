<?php

namespace App\Service;

use App\Entity\PlanNote;
use App\Entity\Player;
use App\Entity\PlayerPosition;
use App\Entity\Team;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class BaseTeamImporter
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @return array{team: Team, created: int, updated: int, removed: int, kept: int, isNewTeam: bool}
     */
    public function syncLiverpool(User $coach): array
    {
        return $this->syncTeam($coach, 'Liverpool FC', $this->getLiverpoolSquad());
    }

    /**
     * @return array{team: Team, created: int, updated: int, removed: int, kept: int, isNewTeam: bool}
     */
    public function syncPsg(User $coach): array
    {
        return $this->syncTeam($coach, 'Paris Saint-Germain', $this->getPsgSquad());
    }

    /**
     * @return array{liverpool: Team, paris: Team}
     */
    public function ensureBaseTeams(User $coach): array
    {
        return [
            'liverpool' => $this->syncLiverpool($coach)['team'],
            'paris' => $this->syncPsg($coach)['team'],
        ];
    }

    /**
     * @param list<array{firstName: string, lastName: string, number: int, position: string}> $squad
     * @return array{team: Team, created: int, updated: int, removed: int, kept: int, isNewTeam: bool}
     */
    private function syncTeam(User $coach, string $club, array $squad): array
    {
        $team = $this->em->getRepository(Team::class)->findOneBy([
            'coach' => $coach,
            'club' => $club,
            'season' => '2025-2026',
        ]);

        $isNewTeam = false;
        if (!$team) {
            $team = new Team();
            $team->setCoach($coach);
            $this->em->persist($team);
            $isNewTeam = true;
        }

        $team->setName($club);
        $team->setClub($club);
        $team->setSeason('2025-2026');

        $existingPlayers = $team->getPlayers()->toArray();
        $created = 0;
        $updated = 0;
        $removed = 0;
        $kept = 0;

        foreach ($squad as $data) {
            $player = $this->findExistingPlayer($existingPlayers, $data);
            if (!$player) {
                $player = new Player();
                $player->setTeam($team);
                $this->em->persist($player);
                $created++;
            } else {
                $updated++;
            }

            $player->setFirstName($data['firstName']);
            $player->setLastName($data['lastName']);
            $player->setNumber($data['number']);
            $player->setPosition($data['position']);
            $player->setTeam($team);
        }

        foreach ($existingPlayers as $player) {
            if ($this->isPlayerInSquad($player, $squad)) {
                continue;
            }

            if ($this->canRemovePlayer($player)) {
                $this->em->remove($player);
                $removed++;
                continue;
            }

            $kept++;
        }

        $this->em->flush();
        $this->em->refresh($team);

        return [
            'team' => $team,
            'created' => $created,
            'updated' => $updated,
            'removed' => $removed,
            'kept' => $kept,
            'isNewTeam' => $isNewTeam,
        ];
    }

    /**
     * @param iterable<Player> $players
     * @param array{firstName: string, lastName: string, number: int, position: string} $data
     */
    private function findExistingPlayer(iterable $players, array $data): ?Player
    {
        foreach ($players as $player) {
            if ($player->getNumber() === $data['number']) {
                return $player;
            }
        }

        foreach ($players as $player) {
            if (
                $this->normalizeName((string) $player->getFirstName()) === $this->normalizeName($data['firstName'])
                && $this->normalizeName((string) $player->getLastName()) === $this->normalizeName($data['lastName'])
            ) {
                return $player;
            }
        }

        return null;
    }

    /**
     * @param list<array{firstName: string, lastName: string, number: int, position: string}> $squad
     */
    private function isPlayerInSquad(Player $player, array $squad): bool
    {
        foreach ($squad as $data) {
            if ($player->getNumber() === $data['number']) {
                return true;
            }

            if (
                $this->normalizeName((string) $player->getFirstName()) === $this->normalizeName($data['firstName'])
                && $this->normalizeName((string) $player->getLastName()) === $this->normalizeName($data['lastName'])
            ) {
                return true;
            }
        }

        return false;
    }

    private function canRemovePlayer(Player $player): bool
    {
        $playerPosition = $this->em->getRepository(PlayerPosition::class)->findOneBy(['player' => $player]);
        $planNote = $this->em->getRepository(PlanNote::class)->findOneBy(['player' => $player]);

        return $playerPosition === null && $planNote === null;
    }

    private function normalizeName(string $value): string
    {
        $normalized = trim(mb_strtolower($value));
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);

        return $ascii !== false ? $ascii : $normalized;
    }

    /**
     * @return list<array{firstName: string, lastName: string, number: int, position: string}>
     */
    private function getLiverpoolSquad(): array
    {
        return [
            ['firstName' => 'Alisson', 'lastName' => 'Becker', 'number' => 1, 'position' => 'GK'],
            ['firstName' => 'Giorgi', 'lastName' => 'Mamardashvili', 'number' => 25, 'position' => 'GK'],
            ['firstName' => 'Virgil', 'lastName' => 'van Dijk', 'number' => 4, 'position' => 'CB'],
            ['firstName' => 'Ibrahima', 'lastName' => 'Konaté', 'number' => 5, 'position' => 'CB'],
            ['firstName' => 'Joe', 'lastName' => 'Gomez', 'number' => 2, 'position' => 'CB'],
            ['firstName' => 'Milos', 'lastName' => 'Kerkez', 'number' => 6, 'position' => 'LB'],
            ['firstName' => 'Andrew', 'lastName' => 'Robertson', 'number' => 26, 'position' => 'LB'],
            ['firstName' => 'Conor', 'lastName' => 'Bradley', 'number' => 12, 'position' => 'RB'],
            ['firstName' => 'Jeremie', 'lastName' => 'Frimpong', 'number' => 30, 'position' => 'RB'],
            ['firstName' => 'Wataru', 'lastName' => 'Endo', 'number' => 3, 'position' => 'CDM'],
            ['firstName' => 'Alexis', 'lastName' => 'Mac Allister', 'number' => 10, 'position' => 'CM'],
            ['firstName' => 'Ryan', 'lastName' => 'Gravenberch', 'number' => 38, 'position' => 'CM'],
            ['firstName' => 'Dominik', 'lastName' => 'Szoboszlai', 'number' => 8, 'position' => 'CAM'],
            ['firstName' => 'Curtis', 'lastName' => 'Jones', 'number' => 17, 'position' => 'CM'],
            ['firstName' => 'Harvey', 'lastName' => 'Elliott', 'number' => 19, 'position' => 'CAM'],
            ['firstName' => 'Stefan', 'lastName' => 'Bajčetić', 'number' => 43, 'position' => 'CDM'],
            ['firstName' => 'Florian', 'lastName' => 'Wirtz', 'number' => 7, 'position' => 'CAM'],
            ['firstName' => 'Mohamed', 'lastName' => 'Salah', 'number' => 11, 'position' => 'RW'],
            ['firstName' => 'Cody', 'lastName' => 'Gakpo', 'number' => 18, 'position' => 'LW'],
            ['firstName' => 'Federico', 'lastName' => 'Chiesa', 'number' => 14, 'position' => 'RW'],
            ['firstName' => 'Alexander', 'lastName' => 'Isak', 'number' => 9, 'position' => 'ST'],
            ['firstName' => 'Hugo', 'lastName' => 'Ekitike', 'number' => 22, 'position' => 'ST'],
        ];
    }

    /**
     * @return list<array{firstName: string, lastName: string, number: int, position: string}>
     */
    private function getPsgSquad(): array
    {
        return [
            ['firstName' => 'Lucas', 'lastName' => 'Chevalier', 'number' => 30, 'position' => 'GK'],
            ['firstName' => 'Matvei', 'lastName' => 'Safonov', 'number' => 39, 'position' => 'GK'],
            ['firstName' => 'Achraf', 'lastName' => 'Hakimi', 'number' => 2, 'position' => 'RB'],
            ['firstName' => 'Marquinhos', 'lastName' => 'Corrêa', 'number' => 5, 'position' => 'CB'],
            ['firstName' => 'Willian', 'lastName' => 'Pacho', 'number' => 51, 'position' => 'CB'],
            ['firstName' => 'Lucas', 'lastName' => 'Beraldo', 'number' => 4, 'position' => 'CB'],
            ['firstName' => 'Nuno', 'lastName' => 'Mendes', 'number' => 25, 'position' => 'LB'],
            ['firstName' => 'Lucas', 'lastName' => 'Hernandez', 'number' => 21, 'position' => 'LB'],
            ['firstName' => 'Fabian', 'lastName' => 'Ruiz', 'number' => 8, 'position' => 'CM'],
            ['firstName' => 'Vitinha', 'lastName' => 'Ferreira', 'number' => 17, 'position' => 'CM'],
            ['firstName' => 'Warren', 'lastName' => 'Zaire-Emery', 'number' => 33, 'position' => 'CDM'],
            ['firstName' => 'Joao', 'lastName' => 'Neves', 'number' => 87, 'position' => 'CDM'],
            ['firstName' => 'Lee', 'lastName' => 'Kang-in', 'number' => 19, 'position' => 'CAM'],
            ['firstName' => 'Senny', 'lastName' => 'Mayulu', 'number' => 24, 'position' => 'CAM'],
            ['firstName' => 'Khvicha', 'lastName' => 'Kvaratskhelia', 'number' => 7, 'position' => 'LW'],
            ['firstName' => 'Ousmane', 'lastName' => 'Dembele', 'number' => 10, 'position' => 'RW'],
            ['firstName' => 'Desire', 'lastName' => 'Doue', 'number' => 14, 'position' => 'LW'],
            ['firstName' => 'Bradley', 'lastName' => 'Barcola', 'number' => 29, 'position' => 'LW'],
            ['firstName' => 'Goncalo', 'lastName' => 'Ramos', 'number' => 9, 'position' => 'ST'],
        ];
    }
}
