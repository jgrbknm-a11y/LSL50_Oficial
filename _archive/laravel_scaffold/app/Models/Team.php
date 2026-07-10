<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    use HasFactory;

    protected $fillable = ['season_id','name','slug','colors_primary','colors_secondary','logo_url'];

    public function season() { return $this->belongsTo(Season::class); }
    public function players() { return $this->hasMany(Player::class); }
    public function sponsors() { return $this->belongsToMany(Sponsor::class, 'sponsor_team'); }
}
