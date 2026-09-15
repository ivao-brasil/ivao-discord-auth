<?php

namespace Tests\Unit\Domain\Entity;

use App\Domain\Entities\Member;
use App\Domain\Entities\RoleRule;
use PHPUnit\Framework\TestCase;
use Tests\Support\IvaoFixtures;

class RoleRuleTest extends TestCase
{
    use IvaoFixtures;

    private function member(array $overrides = []): Member
    {
        return new Member($this->ivaoUser($overrides));
    }

    private function rule(array $conditions): RoleRule
    {
        return new RoleRule(['roles' => ['900']] + $conditions);
    }

    public function testShouldReadLegacyStaffRules()
    {
        $rules = RoleRule::collection([
            ['hash' => 'a1', 'id' => ['900', '901'], 'sulfix' => "BR-WM:\nBR-AWM "],
            ['hash' => 'b2', 'id' => ['902'], 'sulfix' => 'Member'],
            ['hash' => 'c3', 'id' => [], 'sulfix' => 'Member'],
        ]);

        $this->assertCount(2, $rules);
        $this->assertSame(['900', '901'], $rules[0]->getRoles()->all());
        $this->assertSame(['BR-WM', 'BR-AWM'], $rules[0]->toArray()['staff']);
        $this->assertTrue($rules[0]->matches($this->member()));
        $this->assertFalse($rules[0]->matches($this->member(['userStaffPositions' => []])));
        $this->assertTrue($rules[1]->matches($this->member(['userStaffPositions' => []])));
    }

    public function testShouldMatchAnyListedStaffPosition()
    {
        $rule = $this->rule(['staff' => ['BR-DIR', 'BR-WM']]);

        $this->assertTrue($rule->matches($this->member()));
        $this->assertFalse($rule->matches($this->member(['userStaffPositions' => [['id' => 'BR-AOC']]])));
    }

    public function testShouldIgnoreTrialPositionsWhenExcluded()
    {
        $trial = ['userStaffPositions' => [['id' => 'BR-WM', 'onTrial' => true]]];

        $this->assertTrue($this->rule(['staff' => ['BR-WM']])->matches($this->member($trial)));
        $this->assertFalse($this->rule(['staff' => ['BR-WM'], 'includeTrial' => false])->matches($this->member($trial)));
    }

    public function testShouldFilterByDivision()
    {
        $brazilian = $this->member();
        $visitor = $this->member(['divisionId' => 'PT']);

        $onlyBrazil = $this->rule(['divisionMode' => RoleRule::DIVISION_IN, 'divisions' => ['br']]);
        $visitors = $this->rule(['divisionMode' => RoleRule::DIVISION_NOT_IN, 'divisions' => ['BR']]);

        $this->assertTrue($onlyBrazil->matches($brazilian));
        $this->assertFalse($onlyBrazil->matches($visitor));
        $this->assertFalse($visitors->matches($brazilian));
        $this->assertTrue($visitors->matches($visitor));
    }

    public function testShouldRequireMinimumRatings()
    {
        $this->assertTrue($this->rule(['minAtcRating' => 5])->matches($this->member()));
        $this->assertFalse($this->rule(['minAtcRating' => 6])->matches($this->member()));
        $this->assertFalse($this->rule(['minPilotRating' => 5])->matches($this->member()));
        $this->assertFalse($this->rule(['minAtcRating' => 2])->matches($this->member(['rating' => []])));
    }

    public function testShouldRequireMinimumHours()
    {
        $this->assertTrue($this->rule(['minHours' => 10])->matches($this->member()));
        $this->assertFalse($this->rule(['minHours' => 10.5])->matches($this->member()));
    }

    public function testShouldRequireGcaAndVirtualAirlineOwnership()
    {
        $this->assertFalse($this->rule(['requiresGca' => true])->matches($this->member()));
        $this->assertTrue($this->rule(['requiresGca' => true])->matches($this->member(['gcas' => [['id' => 1]]])));
        $this->assertFalse($this->rule(['requiresVaOwnership' => true])->matches($this->member()));
        $this->assertTrue($this->rule(['requiresVaOwnership' => true])->matches($this->member(['ownedVirtualAirlines' => [['id' => 7]]])));
    }

    public function testShouldRequireEveryCondition()
    {
        $rule = $this->rule(['staff' => ['BR-WM'], 'divisionMode' => RoleRule::DIVISION_IN, 'divisions' => ['BR'], 'minAtcRating' => 5]);

        $this->assertTrue($rule->matches($this->member()));
        $this->assertFalse($rule->matches($this->member(['divisionId' => 'PT'])));
    }

    public function testShouldRoundTripThroughArray()
    {
        $rule = $this->rule(['name' => 'Web', 'staff' => ['BR-WM'], 'minHours' => 50, 'requiresGca' => true]);

        $this->assertEquals($rule->toArray(), (new RoleRule($rule->toArray()))->toArray());
    }
}
