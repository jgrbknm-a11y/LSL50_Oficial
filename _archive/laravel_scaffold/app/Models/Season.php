<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Season extends Model
{
    use HasFactory;

    protected $fillable = ['league_id','name','starts_at','ends_at','is_active'];

    public function league() { return $this->belongsTo(League::class); }
    public function teams() { return $this->hasMany(Team::class); }
    public function games() { return $this->hasMany(Game::class); }
}
