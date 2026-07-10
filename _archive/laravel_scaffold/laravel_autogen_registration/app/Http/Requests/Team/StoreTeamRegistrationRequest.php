<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;

class StoreTeamRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'team_name' => 'required|string|max:120',
            'contact_name' => 'required|string|max:120',
            'contact_email' => 'required|email|max:160',
            'contact_phone' => 'nullable|string|max:40',
            'preferred_abbr' => 'nullable|string|max:8',
            'home_city' => 'nullable|string|max:160',
            'branding_preferences' => 'nullable|array',
        ];
    }
}
