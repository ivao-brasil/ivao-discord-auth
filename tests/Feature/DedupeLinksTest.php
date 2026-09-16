<?php

namespace Tests\Feature;

use App\ConsentmentModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DedupeLinksTest extends TestCase
{
    use RefreshDatabase;

    private function link(string $vid, string $discordId, bool $active = true): ConsentmentModel
    {
        return ConsentmentModel::create([
            'userVid' => $vid, 'discordId' => $discordId, 'nickName' => 'x',
            'roles' => '', 'division' => 'BR', 'status' => $active,
        ]);
    }

    public function test_it_keeps_the_newest_link_of_each_repeated_account()
    {
        $old = $this->link('123456', '555');
        $newer = $this->link('123456', '555');
        $untouched = $this->link('222222', '556');

        $this->artisan('discord:dedupe-links')->assertSuccessful();

        $this->assertEquals(0, $old->fresh()->status);
        $this->assertEquals(1, $newer->fresh()->status);
        $this->assertEquals(1, $untouched->fresh()->status);
    }

    public function test_it_leaves_a_vid_with_two_discord_accounts_for_review()
    {
        $first = $this->link('123456', '555');
        $second = $this->link('123456', '556');

        $this->artisan('discord:dedupe-links')
            ->expectsOutputToContain('linked to more than one Discord account')
            ->assertSuccessful();

        $this->assertEquals(1, $first->fresh()->status);
        $this->assertEquals(1, $second->fresh()->status);
    }

    public function test_dry_run_changes_nothing()
    {
        $old = $this->link('123456', '555');
        $this->link('123456', '555');

        $this->artisan('discord:dedupe-links --dry-run')->assertSuccessful();

        $this->assertEquals(1, $old->fresh()->status);
    }
}
