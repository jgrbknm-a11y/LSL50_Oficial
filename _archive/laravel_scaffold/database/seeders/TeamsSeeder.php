<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TeamsSeeder extends Seeder
{
    public function run(): void
    {
        $teams = [
            ['name' => 'Caribeños', 'slug' => Str::slug('Caribeños')],
            ['name' => 'Águilas',   'slug' => Str::slug('Águilas')],
            ['name' => 'Tiburones', 'slug' => Str::slug('Tiburones')],
        ];

        foreach ($teams as $t) {
            DB::table('teams')->updateOrInsert(
                ['slug' => $t['slug']],
                ['name' => $t['name'], 'league_id'=>1, 'season_id'=>1]
            );
        }
    }
}