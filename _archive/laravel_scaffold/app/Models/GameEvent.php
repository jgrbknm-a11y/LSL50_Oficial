<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GameEvent extends Model
{
    use HasFactory;

    protected $fillable = ['game_id','team_id','player_id','inning','half','type','rbi','meta'];

    protected $casts = ['meta' => 'array'];

    public function game() { return $this->belongsTo(Game::class); }
    public function team() { return $this->belongsTo(Team::class); }
    public function player() { return $this->belongsTo(Player::class); }
}
