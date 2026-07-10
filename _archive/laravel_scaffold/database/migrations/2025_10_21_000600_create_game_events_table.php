<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('game_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('game_id');
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('player_id')->nullable();
            $table->unsignedTinyInteger('inning')->default(1);
            $table->enum('half', ['top','bottom'])->default('top');
            $table->string('type'); // single,double,triple,hr,bb,k,out,run,error,sac,fielder_choice
            $table->unsignedTinyInteger('rbi')->default(0);
            $table->json('meta')->nullable(); // extra details
            $table->timestamps();

            $table->foreign('game_id')->references('id')->on('games')->onDelete('cascade');
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
            $table->foreign('player_id')->references('id')->on('players')->onDelete('set null');
        });
    }
    public function down(): void {
        Schema::dropIfExists('game_events');
    }
};
