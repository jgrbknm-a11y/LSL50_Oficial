<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamRegistration extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_name','contact_name','contact_email','contact_phone','preferred_abbr','home_city',
        'branding_preferences','status','approved_at','approved_by','team_id'
    ];

    protected $casts = [
        'branding_preferences' => 'array',
        'approved_at' => 'datetime',
    ];
}
