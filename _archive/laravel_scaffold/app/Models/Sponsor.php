<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sponsor extends Model
{
    use HasFactory;

    protected $fillable = ['name','level','logo_url','website'];

    public function teams() { return $this->belongsToMany(Team::class, 'sponsor_team'); }
}
