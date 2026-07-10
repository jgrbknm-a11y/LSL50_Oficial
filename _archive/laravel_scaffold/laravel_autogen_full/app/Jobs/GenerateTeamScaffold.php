<?php

namespace App\Jobs;

use App\Models\Team;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\TeamPaletteService;
use App\Services\TeamFileWriter;

class GenerateTeamScaffold implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $teamId) {}

    public function handle(): void
    {
        $team = Team::find($this->teamId);
        if (!$team) return;

        // 1) Colors / branding defaults if missing
        $branding = $team->branding ?? [];
        if (!isset($branding['primary_color'])) {
            $palette = TeamPaletteService::pick();
            $branding['primary_color'] = $palette['primary'];
            $branding['secondary_color'] = $palette['secondary'];
            $branding['accent_color'] = $palette['accent'];
        }

        if (!isset($branding['logo']['primary'])) {
            $branding['logo']['primary'] = "/storage/teams/{$team->slug}/logo.png";
        }

        $team->branding = $branding;
        $team->primary_color = $branding['primary_color'] ?? null;
        $team->secondary_color = $branding['secondary_color'] ?? null;
        $team->accent_color = $branding['accent_color'] ?? null;
        $team->save();

        // 2) Write team JSON + placeholders (banner, badge) to storage/public
        TeamFileWriter::writeAll($team);
    }
}
