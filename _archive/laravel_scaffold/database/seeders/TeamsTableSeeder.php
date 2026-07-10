<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use App\Models\Team;

class TeamsTableSeeder extends Seeder
{
    public function run(): void
    {
        // Read teams from storage/seed/teams.json
        $path = storage_path('seed/teams.json');
        if (!file_exists($path)) {
            $this->command->warn('No seed/teams.json found at ' . $path);
            return;
        }

        $json = file_get_contents($path);
        $data = json_decode($json, true);

        if (!isset($data['teams'])) {
            $this->command->warn('Invalid teams.json format.');
            return;
        }

        foreach ($data['teams'] as $t) {
            Team::updateOrCreate(
                ['slug' => $t['slug']],
                [
                    'team_name' => $t['team_name'],
                    'team_name_short' => $t['team_name_short'] ?? null,
                    'team_abbr' => $t['team_abbr'] ?? null,
                    'league' => $t['league'] ?? null,
                    'status' => $t['status'] ?? 'active',
                    'founded_year' => $t['founded_year'] ?? null,
                    'home_city' => $t['home_city'] ?? null,
                    'primary_color' => ($t['branding']['primary_color'] ?? null),
                    'secondary_color' => ($t['branding']['secondary_color'] ?? null),
                    'accent_color' => ($t['branding']['accent_color'] ?? null),
                    'branding' => $t['branding'] ?? [],
                    'uniforms' => $t['uniforms'] ?? [],
                    'descriptions' => $t['descriptions'] ?? [],
                    'contacts' => $t['contacts'] ?? [],
                    'social' => $t['social'] ?? [],
                    'season_data' => $t['season_data'] ?? [],
                ]
            );
        }
    }
}
