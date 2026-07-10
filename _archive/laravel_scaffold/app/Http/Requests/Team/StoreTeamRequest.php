<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;

class StoreTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Replace with policy/gate if needed
    }

    public function rules(): array
    {
        return [
            'slug' => 'nullable|string|max:64|unique:teams,slug',
            'team_name' => 'required|string|max:120',
            'team_name_short' => 'nullable|string|max:120',
            'team_abbr' => 'nullable|string|max:8',
            'league' => 'nullable|string|max:160',
            'status' => 'nullable|in:active,inactive,archived',
            'founded_year' => 'nullable|integer|min:1800|max:2100',
            'home_city' => 'nullable|string|max:160',
            'primary_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'secondary_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'accent_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'branding' => 'nullable|array',
            'uniforms' => 'nullable|array',
            'descriptions' => 'nullable|array',
            'contacts' => 'nullable|array',
            'social' => 'nullable|array',
            'season_data' => 'nullable|array',
        ];
    }
}
