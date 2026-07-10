<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TeamsPlayersSeeder extends Seeder
{
    public function run(): void
    {
        $seasonId = DB::table('seasons')->where('is_active', true)->orderByDesc('id')->value('id');

        $teams = [
            ['name'=>'Caribeños','slug'=>'caribenos','colors_primary'=>'#0033A0','colors_secondary'=>'#FFB81C'],
            ['name'=>'Broward Titans','slug'=>'broward-titans','colors_primary'=>'#111827','colors_secondary'=>'#9CA3AF'],
        ];

        foreach ($teams as $t) {
            $teamId = DB::table('teams')->insertGetId([
                'season_id' => $seasonId,
                'name' => $t['name'],
                'slug' => $t['slug'],
                'colors_primary' => $t['colors_primary'],
                'colors_secondary' => $t['colors_secondary'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Sample players 1..5
            for ($i=1; $i<=5; $i++) {
                DB::table('players')->insert([
                    'team_id' => $teamId,
                    'first_name' => 'Jugador',
                    'last_name' => $i,
                    'number' => $i,
                    'position' => 'UTIL',
                    'active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
