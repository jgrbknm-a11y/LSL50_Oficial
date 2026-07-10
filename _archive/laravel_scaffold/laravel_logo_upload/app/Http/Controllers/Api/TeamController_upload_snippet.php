<?php

// Add these methods into App\Http\Controllers\Api\TeamController (same trait as logo)

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\Storage;
use App\Http\Requests\Team\UploadTeamLogoRequest;
use App\Http\Requests\Team\UploadTeamBannerRequest;
use App\Http\Requests\Team\UploadTeamBadgeRequest;
use App\Models\Team;
use Illuminate\Http\UploadedFile;

trait TeamLogoUpload
{
    public function uploadLogo(UploadTeamLogoRequest $request, string $slug)
    {
        $team = Team::where('slug', $slug)->firstOrFail();
        /** @var UploadedFile $file */
        $file = $request->file('logo');
        return $this->storeTeamMedia($team, $file, 'logo', ['png','jpg','jpeg','webp','svg']);
    }

    public function uploadBanner(UploadTeamBannerRequest $request, string $slug)
    {
        $team = Team::where('slug', $slug)->firstOrFail();
        /** @var UploadedFile $file */
        $file = $request->file('image');
        return $this->storeTeamMedia($team, $file, 'banner', ['png','jpg','jpeg','webp','svg']);
    }

    public function uploadBadge(UploadTeamBadgeRequest $request, string $slug)
    {
        $team = Team::where('slug', $slug)->firstOrFail();
        /** @var UploadedFile $file */
        $file = $request->file('image');
        return $this->storeTeamMedia($team, $file, 'badge', ['png','jpg','jpeg','webp','svg']);
    }

    protected function storeTeamMedia(Team $team, UploadedFile $file, string $type, array $allowedExt)
    {
        $dir = "public/teams/{$team->slug}";
        Storage::makeDirectory($dir);

        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, $allowedExt)) {
            $ext = 'png';
        }

        $filename = "{$type}.{$ext}";
        $path = Storage::putFileAs($dir, $file, $filename);
        $publicUrl = "/storage/teams/{$team->slug}/{$filename}";

        $branding = $team->branding ?? [];
        if (!isset($branding[$type])) $branding[$type] = [];
        if ($type === 'logo') {
            if (!isset($branding['logo'])) $branding['logo'] = [];
            $branding['logo']['primary'] = $publicUrl;
        } else {
            $branding[$type]['primary'] = $publicUrl;
        }

        $team->branding = $branding;
        $team->save();

        return response()->json([
            'status' => 'ok',
            'message' => ucfirst($type).' actualizado',
            'data' => [
                'slug' => $team->slug,
                f'{type}_url' => $publicUrl
            ]
        ]);
    }
}
