<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;

class UploadTeamBadgeRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Ajusta según tu auth (admin/manager)
        return true;
    }

    public function rules(): array
    {
        return [
            'image' => [
                'required',
                'file',
                'mimes:png,jpg,jpeg,webp,svg',
                'max:6144' // 6MB
            ],
        ];
    }
}
