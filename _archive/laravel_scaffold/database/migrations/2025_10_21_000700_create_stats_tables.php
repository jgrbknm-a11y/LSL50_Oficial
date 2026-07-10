<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('stats_players', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('season_id');
            $table->unsignedBigInteger('player_id');
            $table->unsignedInteger('g')->default(0);   // games
            $table->unsignedInteger('ab')->default(0);  // at-bats
            $table->unsignedInteger('h')->default(0);   // hits
            $table->unsignedInteger('r')->default(0);   // runs
            $table->unsignedInteger('hr')->default(0);  // home runs
            $table->unsignedInteger('rbi')->default(0);
            $table->unsignedInteger('bb')->default(0);
            $table->unsignedInteger('k')->default(0);
            $table->unsignedInteger('sb')->default(0);
            $table->timestamps();

            $table->unique(['season_id','player_id']);
            $table->foreign('season_id')->references('id')->on('seasons')->onDelete('cascade');
            $table->foreign('player_id')->references('id')->on('players')->onDelete('cascade');
        });

        Schema::create('stats_teams', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('season_id');
            $table->unsignedBigInteger('team_id');
            $table->unsignedInteger('w')->default(0);
            $table->unsignedInteger('l')->default(0);
            $table->unsignedInteger('t')->default(0);
            $table->unsignedInteger('rf')->default(0); // runs for
            $table->unsignedInteger('ra')->default(0); // runs against
            $table->timestamps();

            $table->unique(['season_id','team_id']);
            $table->foreign('season_id')->references('id')->on('seasons')->onDelete('cascade');
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
        });

        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('season_id');
            $table->unsignedBigInteger('team_id');
            $table->unsignedInteger('rank')->default(0);
            $table->timestamps();

            $table->unique(['season_id','team_id']);
            $table->foreign('season_id')->references('id')->on('seasons')->onDelete('cascade');
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
        });
    }
    public function down(): void {
        Schema::dropIfExists('positions');
        Schema::dropIfExists('stats_teams');
        Schema::dropIfExists('stats_players');
    }
};
