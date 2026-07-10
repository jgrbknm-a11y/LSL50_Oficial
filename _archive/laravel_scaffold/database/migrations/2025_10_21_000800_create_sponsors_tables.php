<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('sponsors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('level')->nullable(); // Gold, Silver, etc.
            $table->string('logo_url')->nullable();
            $table->string('website')->nullable();
            $table->timestamps();
        });

        Schema::create('sponsor_team', function (Blueprint $table) {
            $table->unsignedBigInteger('sponsor_id');
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('season_id')->nullable();
            $table->primary(['sponsor_id','team_id']);
            $table->foreign('sponsor_id')->references('id')->on('sponsors')->onDelete('cascade');
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
            $table->foreign('season_id')->references('id')->on('seasons')->onDelete('cascade');
        });
    }
    public function down(): void {
        Schema::dropIfExists('sponsor_team');
        Schema::dropIfExists('sponsors');
    }
};
