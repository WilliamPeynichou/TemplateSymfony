<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Callup;
use App\Entity\CallupPlayer;
use App\Entity\Fixture;
use App\Entity\Player;
use App\Entity\User;
use App\Repository\CallupPlayerRepository;
use App\Repository\FixtureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/matches/{fixtureId}/callup', requirements: ['fixtureId' => '\d+'])]
class CallupController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FixtureRepository $fixtureRepository,
        private readonly CallupPlayerRepository $callupPlayerRepository,
    ) {}

    #[Route('', name: 'app_callup_edit', methods: ['GET'])]
    public function edit(int $fixtureId): Response
    {
        $fixture = $this->getFixtureOrDeny($fixtureId);
        $callup  = $fixture->getCallup() ?? new Callup();

        $players = $fixture->getTeam()?->getPlayers()->toArray() ?? [];
        usort($players, fn (Player $a, Player $b) => $a->getNumber() <=> $b->getNumber());

        $assignedIds = [];
        foreach ($callup->getCallupPlayers() as $cp) {
            $assignedIds[$cp->getPlayer()?->getId()] = $cp;
        }

        // Joueurs non encore assignés → not_called par défaut dans la vue
        $unassigned = array_filter($players, fn (Player $p) => !isset($assignedIds[$p->getId()]));

        return $this->render('match/callup.html.twig', [
            'fixture'     => $fixture,
            'callup'      => $callup,
            'players'     => $players,
            'unassigned'  => array_values($unassigned),
            'starters'    => $callup->getStarters(),
            'substitutes' => $callup->getSubstitutes(),
            'notCalled'   => $callup->getNotCalled(),
            'absent'      => $callup->getAbsent(),
            'roles'       => CallupPlayer::ROLES,
            'reasons'     => CallupPlayer::REASON_LABELS,
        ]);
    }

    #[Route('/save', name: 'app_callup_save', methods: ['POST'])]
    public function save(int $fixtureId, Request $request): JsonResponse
    {
        $fixture = $this->getFixtureOrDeny($fixtureId);
        $data    = json_decode((string) $request->getContent(), true);

        if (!\is_array($data) || !isset($data['players']) || !\is_array($data['players'])) {
            return new JsonResponse(['error' => 'invalid_payload'], 400);
        }

        $callup = $fixture->getCallup();
        if ($callup === null) {
            $callup = new Callup();
            $callup->setFixture($fixture);
            $fixture->setCallup($callup);
            $this->em->persist($callup);
        }
        $callup->touch();

        // Index existing CallupPlayers by player_id for quick lookup
        $existing = [];
        foreach ($callup->getCallupPlayers() as $cp) {
            $existing[$cp->getPlayer()?->getId()] = $cp;
        }

        $seen = [];
        foreach ($data['players'] as $entry) {
            $playerId = (int) ($entry['playerId'] ?? 0);
            $role     = (string) ($entry['role'] ?? CallupPlayer::ROLE_NOT_CALLED);
            $reason   = ($entry['reason'] ?? '') ?: null;
            $notes    = ($entry['notes'] ?? '') ?: null;
            $jersey   = isset($entry['jerseyNumber']) ? (int) $entry['jerseyNumber'] : null;

            if ($playerId <= 0 || !\in_array($role, CallupPlayer::ROLES, true)) {
                continue;
            }

            if (isset($existing[$playerId])) {
                $cp = $existing[$playerId];
            } else {
                $player = $this->em->getReference(Player::class, $playerId);
                $cp = new CallupPlayer();
                $cp->setPlayer($player);
                $callup->addCallupPlayer($cp);
                $this->em->persist($cp);
            }

            $cp->setRole($role);
            $cp->setReason($reason);
            $cp->setNotes($notes);
            $cp->setJerseyNumber($jersey);

            $seen[$playerId] = true;
        }

        // Supprimer les joueurs retirés
        foreach ($existing as $playerId => $cp) {
            if (!isset($seen[$playerId])) {
                $callup->removeCallupPlayer($cp);
                $this->em->remove($cp);
            }
        }

        $this->em->flush();

        return new JsonResponse([
            'ok' => true,
            'starters'    => \count($callup->getStarters()),
            'substitutes' => \count($callup->getSubstitutes()),
            'absent'      => \count($callup->getAbsent()),
        ]);
    }

    private function getFixtureOrDeny(int $fixtureId): Fixture
    {
        $fixture = $this->fixtureRepository->find($fixtureId);
        if (!$fixture instanceof Fixture) {
            throw $this->createNotFoundException('Match introuvable.');
        }

        /** @var User $coach */
        $coach = $this->getUser();
        if ($fixture->getCoach()?->getId() !== $coach->getId()) {
            throw $this->createAccessDeniedException();
        }

        return $fixture;
    }
}
