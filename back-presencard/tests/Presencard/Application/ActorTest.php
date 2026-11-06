<?php

declare(strict_types=1);

namespace App\Tests\Presencard\Application;

use App\Presencard\Application\Actor;
use App\Tests\Presencard\Support\PresencardMother;
use PHPUnit\Framework\TestCase;

final class ActorTest extends TestCase
{
    public function testAdministratorManagesEverythingAndHasNoOwnerFilter(): void
    {
        $actor = Actor::administrator(99);

        $this->assertTrue($actor->canManage(PresencardMother::create('aaaaaaaaaaaa', 'A', 'B', 'a@b.co', 1)));
        $this->assertNull($actor->ownerFilter());
        $this->assertTrue($actor->isAdministrator());
    }

    public function testRegularUserManagesOnlyTheirOwnAndIsFilteredByThemselves(): void
    {
        $actor = Actor::regularUser(5);

        $this->assertTrue($actor->canManage(PresencardMother::create('aaaaaaaaaaaa', 'A', 'B', 'a@b.co', 5)));
        $this->assertFalse($actor->canManage(PresencardMother::create('bbbbbbbbbbbb', 'A', 'B', 'a@b.co', 6)));
        $this->assertSame(5, $actor->ownerFilter());
        $this->assertFalse($actor->isAdministrator());
    }
}
