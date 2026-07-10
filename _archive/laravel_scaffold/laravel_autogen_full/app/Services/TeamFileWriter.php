<?php

namespace App\Services;

use App\Models\Team;
use Illuminate\Support\Facades\Storage;

class TeamFileWriter
{
    public static function writeAll(Team $team): void
    {
        $slug = $team->slug;
        $dir = "public/teams/{$slug}";
        Storage::makeDirectory($dir);

        // 1) team.json (public)
        $json = [
            'id' => $team->id,
            'slug' => $team->slug,
            'team_name' => $team->team_name,
            'team_name_short' => $team->team_name_short,
            'team_abbr' => $team->team_abbr,
            'league' => $team->league,
            'status' => $team->status,
            'founded_year' => $team->founded_year,
            'home_city' => $team->home_city,
            'branding' => $team->branding,
            'uniforms' => $team->uniforms,
            'descriptions' => $team->descriptions,
            'contacts' => $team->contacts,
            'social' => $team->social,
            'season_data' => $team->season_data,
            'updated_at' => optional($team->updated_at)->toIso8601String(),
        ];
        Storage::put("{$dir}/team.json", json_encode($json, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));

        // 2) placeholder banner (banderin) 1200x400
        $banner = base64_decode(config('team_defaults.placeholder_banner_base64'));
        Storage::put("{$dir}/banner.png", $banner);

        // 3) placeholder badge (escudo) 800x800
        $badge = base64_decode(config('team_defaults.placeholder_badge_base64'));
        Storage::put("{$dir}/badge.png", $badge);
    }
}
