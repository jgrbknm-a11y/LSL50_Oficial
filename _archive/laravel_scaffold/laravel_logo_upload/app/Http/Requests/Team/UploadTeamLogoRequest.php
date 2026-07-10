<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;

class UploadTeamLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Ajusta según tu auth: permitir solo admin o manager
        return true;
    }

    public function rules(): array
    {
        return [
            'logo' => [
                'required',
                'file',
                'mimes:png,jpg,jpeg,webp,svg',
                'max:4096' // 4MB
            ],
        ];
    }
}
