<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'team_name' => $this->team_name,
            'team_name_short' => $this->team_name_short,
            'team_abbr' => $this->team_abbr,
            'league' => $this->league,
            'status' => $this->status,
            'founded_year' => $this->founded_year,
            'home_city' => $this->home_city,
            'primary_color' => $this->primary_color,
            'secondary_color' => $this->secondary_color,
            'accent_color' => $this->accent_color,
            'branding' => $this->branding,
            'uniforms' => $this->uniforms,
            'descriptions' => $this->descriptions,
            'contacts' => $this->contacts,
            'social' => $this->social,
            'season_data' => $this->season_data,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
