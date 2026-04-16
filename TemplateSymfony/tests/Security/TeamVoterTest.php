<?php

namespace App\Tests\Security;

use App\Entity\Team;
use App\Entity\User;
use App\Security\TeamVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class TeamVoterTest extends TestCase
{
    public function testCoachCanAccessOwnTeam(): void
    {
        $coach = $this->buildUserWithId(10);
        $team = (new Team())->setName('A')->setCoach($coach);

        $token = new UsernamePasswordToken($coach, 'main');
        $voter = new TeamVoter();

        self::assertSame(TeamVoter::ACCESS_GRANTED, $voter->vote($token, $team, ['COACH']));
    }

    public function testOtherCoachCannotAccessTeam(): void
    {
        $coach = $this->buildUserWithId(10);
        $otherCoach = $this->buildUserWithId(11);
        $team = (new Team())->setName('A')->setCoach($coach);

        $token = new UsernamePasswordToken($otherCoach, 'main');
        $voter = new TeamVoter();

        self::assertSame(TeamVoter::ACCESS_DENIED, $voter->vote($token, $team, ['COACH']));
    }

    private function buildUserWithId(int $id): User
    {
        $user = new User();
        $reflection = new \ReflectionProperty(User::class, 'id');
        $reflection->setValue($user, $id);

        return $user;
    }
}
