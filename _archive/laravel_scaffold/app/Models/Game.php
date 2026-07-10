<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Game extends Model
{
    use HasFactory;

    protected $fillable = ['season_id','home_team_id','away_team_id','starts_at','location','status','home_score','away_score'];

    public function season() { return $this->belongsTo(Season::class); }
    public function homeTeam() { return $this->belongsTo(Team::class, 'home_team_id'); }
    public function awayTeam() { return $this->belongsTo(Team::class, 'away_team_id'); }
    public function events() { return $this->hasMany(GameEvent::class); }
}
