<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Replace with policy/gate if needed
    }

    public function rules(): array
    {
        return [
            'slug' => [
                'sometimes','string','max:64',
                Rule::unique('teams', 'slug')->ignore($this->route('slug'), 'slug')
            ],
            'team_name' => 'sometimes|string|max:120',
            'team_name_short' => 'sometimes|nullable|string|max:120',
            'team_abbr' => 'sometimes|nullable|string|max:8',
            'league' => 'sometimes|nullable|string|max:160',
            'status' => 'sometimes|in:active,inactive,archived',
            'founded_year' => 'sometimes|nullable|integer|min:1800|max:2100',
            'home_city' => 'sometimes|nullable|string|max:160',
            'primary_color' => 'sometimes|nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'secondary_color' => 'sometimes|nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'accent_color' => 'sometimes|nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'branding' => 'sometimes|nullable|array',
            'uniforms' => 'sometimes|nullable|array',
            'descriptions' => 'sometimes|nullable|array',
            'contacts' => 'sometimes|nullable|array',
            'social' => 'sometimes|nullable|array',
            'season_data' => 'sometimes|nullable|array',
        ];
    }
}
