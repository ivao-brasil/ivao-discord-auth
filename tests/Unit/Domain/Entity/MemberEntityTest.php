<?php

namespace Tests\Unit\Domain\Entity;

use PHPUnit\Framework\TestCase;

use App\Domain\Entities\Member;

class MemberEntityTest extends TestCase
{
    private function generateNormalMember() {
        $memberData = [
            'vid' => '123456',
            'firstname' => 'Fulano da Silva',
            'division' => 'BR',
            'staff' => null
        ];

        return new Member($memberData);
    }

    private function generateSinglePositionStaff() {
        $memberData = [
            'vid' => '123456',
            'firstname' => 'Ciclano da Silva',
            'division' => 'BR',
            'staff' => 'ZZ-WM'
        ];

        return new Member($memberData);
    }

    private function generateMultiplePositionStaff() {
        $memberData = [
            'vid' => '123456',
            'firstname' => 'Beltrano da Silva',
            'division' => 'BR',
            'staff' => 'ZZ-WM:ZZ-DIR'
        ];

        return new Member($memberData);
    }

    public function testShouldGenerateCorrectNickNameForMember()
    {
        $member = $this->generateNormalMember();
        $this->assertEquals($member->generateNickname(), 'Fulano - 123456');
    }

    public function testShouldGenerateCorrectNickNameForStaffWithSinglePosition() {
        $member = $this->generateSinglePositionStaff();
        $this->assertEquals($member->generateNickname(), 'Ciclano | ZZ-WM');
    }

    public function testShouldGenerateCorrectNickNameForStaffWithMultiplePositions() {
        $member = $this->generateMultiplePositionStaff();
        $this->assertEquals($member->generateNickname(), 'Beltrano | ZZ-WM ZZ-DIR');
    }

    public function testShouldCertifyThatReadNormalMemberCorrectly(){
        $member = $this->generateNormalMember();
        $this->assertFalse($member->isStaff());
    }

    public function testShouldCertifyThatReadStaffMemberCorrectly() {
        $member = $this->generateSinglePositionStaff();
        $this->assertTrue($member->isStaff());
    }
}
