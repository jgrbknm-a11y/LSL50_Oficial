<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatsTeam extends Model
{
    use HasFactory;

    protected $table = 'stats_teams';

    protected $fillable = ['season_id','team_id','w','l','t','rf','ra'];

    public function team() { return $this->belongsTo(Team::class); }
    public function season() { return $this->belongsTo(Season::class); }
}
