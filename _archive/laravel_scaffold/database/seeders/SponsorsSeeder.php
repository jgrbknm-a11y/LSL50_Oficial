<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SponsorsSeeder extends Seeder
{
    public function run(): void
    {
        $s1 = DB::table('sponsors')->insertGetId([
            'name' => 'RC Global Health LLC',
            'level' => 'Gold',
            'website' => 'https://rcglobalhealth.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $seasonId = DB::table('seasons')->where('is_active', true)->orderByDesc('id')->value('id');
        $teamId = DB::table('teams')->first()->id ?? null;
        if ($teamId) {
            DB::table('sponsor_team')->insert([
                'sponsor_id' => $s1,
                'team_id' => $teamId,
                'season_id' => $seasonId,
            ]);
        }
    }
}
