<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Player extends Model
{
    use HasFactory;

    protected $fillable = ['team_id','first_name','last_name','document_id','email','phone','number','position','birthdate','active'];

    public function team() { return $this->belongsTo(Team::class); }
    public function statsSeason() { return $this->hasOne(StatsPlayer::class); }
}
