<?php

namespace App\Jobs;

use App\Models\Team;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ProvisionTeamAssets implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $teamId) {}

    public function handle(): void
    {
        $team = Team::find($this->teamId);
        if (!$team) return;

        $slug = $team->slug;
        $dir = "public/teams/{$slug}";
        Storage::makeDirectory($dir);

        // Place default placeholder logo
        $placeholder = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAE0lEQVR42mP8z8DwnwEIFQwMAAA7WgF8R2JMuQAAAABJRU5ErkJggg==";
        $raw = base64_decode(explode(',', $placeholder, 1)[0] ?? '');
        Storage::put("{$dir}/logo.png", $raw);

        // Update team branding path to public URL
        $branding = $team->branding ?? [];
        $branding['logo']['primary'] = "/storage/teams/{$slug}/logo.png";
        $team->branding = $branding;
        $team->save();
    }
}
