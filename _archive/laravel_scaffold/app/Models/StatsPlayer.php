<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatsPlayer extends Model
{
    use HasFactory;

    protected $table = 'stats_players';

    protected $fillable = ['season_id','player_id','g','ab','h','r','hr','rbi','bb','k','sb'];

    public function player() { return $this->belongsTo(Player::class); }
    public function season() { return $this->belongsTo(Season::class); }

    public function getAvgAttribute() {
        return $this->ab > 0 ? round($this->h / $this->ab, 3) : 0;
    }
}
