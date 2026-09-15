<?php

namespace Tests\Unit\Domain\Entity;

use App\Domain\Entities\Member;
use PHPUnit\Framework\TestCase;

class MemberEntityTest extends TestCase
{
    private function ivaoUser(array $overrides = []): array
    {
        return array_merge([
            'id' => 123456,
            'firstName' => 'Fulano da Silva',
            'lastName' => 'Souza',
            'divisionId' => 'BR',
            'rating' => ['networkRating' => ['id' => Member::STATUS_ACTIVE]],
            'hours' => [
                ['type' => 'pilot', 'hours' => 7200],
                ['type' => 'atc', 'hours' => 3600],
                ['type' => 'staff', 'hours' => 999999],
            ],
            'userStaffPositions' => [],
        ], $overrides);
    }

    private function staffPositions(string ...$ids): array
    {
        return array_map(fn ($id) => ['id' => $id], $ids);
    }

    public function testShouldGenerateCorrectNickNameForMember()
    {
        $member = new Member($this->ivaoUser());
        $this->assertEquals('Fulano - 123456', $member->generateNickname());
    }

    public function testShouldGenerateCorrectNickNameForStaffWithSinglePosition()
    {
        $member = new Member($this->ivaoUser([
            'firstName' => 'Ciclano da Silva',
            'userStaffPositions' => $this->staffPositions('ZZ-WM'),
        ]));
        $this->assertEquals('Ciclano | ZZ-WM', $member->generateNickname());
    }

    public function testShouldGenerateCorrectNickNameForStaffWithMultiplePositions()
    {
        $member = new Member($this->ivaoUser([
            'firstName' => 'Beltrano da Silva',
            'userStaffPositions' => $this->staffPositions('ZZ-WM', 'ZZ-DIR'),
        ]));
        $this->assertEquals('Beltrano | ZZ-WM ZZ-DIR', $member->generateNickname());
    }

    public function testShouldCertifyThatReadNormalMemberCorrectly()
    {
        $member = new Member($this->ivaoUser());
        $this->assertFalse($member->isStaff());
        $this->assertSame('123456', $member->getVid());
        $this->assertSame('BR', $member->getDivision());
    }

    public function testShouldCertifyThatReadStaffMemberCorrectly()
    {
        $member = new Member($this->ivaoUser(['userStaffPositions' => $this->staffPositions('BR-WM')]));
        $this->assertTrue($member->isStaff());
        $this->assertEquals(['BR-WM'], $member->getStaff()->all());
    }

    public function testShouldPrefixHqPositionsAndListThemAfterDivisionPositions()
    {
        $member = new Member($this->ivaoUser([
            'firstName' => 'Mikhael da Silva',
            'userStaffPositions' => [
                ['id' => 'WD6', 'connectAs' => 'IVAO-WD6'],
                ['id' => 'BR-MA1', 'connectAs' => 'BR-MA1'],
                ['id' => 'BR-WM', 'connectAs' => 'BR-WM'],
            ],
        ]));

        $this->assertEquals('Mikhael | BR-MA1 BR-WM IVAO-WD6', $member->generateNickname());
        $this->assertEquals(['WD6', 'BR-MA1', 'BR-WM'], $member->getStaff()->all());
    }

    public function testShouldDropPositionsFromTheEndWhenNicknameIsTooLong()
    {
        $member = new Member($this->ivaoUser([
            'firstName' => 'Maximiliano',
            'userStaffPositions' => $this->staffPositions('BR-DIR', 'BR-ADIR', 'BR-WM', 'BR-AWM'),
        ]));

        $nickname = $member->generateNickname();
        $this->assertEquals('Maximiliano | BR-DIR BR-ADIR', $nickname);
        $this->assertLessThanOrEqual(Member::NICKNAME_MAX_LENGTH, mb_strlen($nickname));
    }

    public function testShouldCutNicknameWhenASinglePositionDoesNotFit()
    {
        $member = new Member($this->ivaoUser([
            'firstName' => 'Bartholomeuzinhooliveira',
            'userStaffPositions' => $this->staffPositions('BR-ADIR'),
        ]));

        $this->assertSame(Member::NICKNAME_MAX_LENGTH, mb_strlen($member->generateNickname()));
    }

    public function testShouldSumPilotAndAtcHoursOnly()
    {
        $member = new Member($this->ivaoUser());
        $this->assertEquals(3, $member->getTotalHours());
    }

    public function testShouldTreatMissingHoursAsZero()
    {
        $member = new Member($this->ivaoUser(['hours' => []]));
        $this->assertEquals(0, $member->getTotalHours());
    }

    public function testShouldReadAccountStatusFromNetworkRating()
    {
        $this->assertTrue((new Member($this->ivaoUser()))->isActive());

        $suspended = new Member($this->ivaoUser(['rating' => ['networkRating' => ['id' => Member::STATUS_SUSPENDED]]]));
        $this->assertTrue($suspended->isSuspended());
        $this->assertFalse($suspended->isActive());

        $inactive = new Member($this->ivaoUser(['rating' => ['networkRating' => ['id' => Member::STATUS_INACTIVE]]]));
        $this->assertTrue($inactive->isInactive());
        $this->assertSame('inactive', $inactive->getAccountStatusReason());
    }
}
