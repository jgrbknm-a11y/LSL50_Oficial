<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('team_name');
            $table->string('team_name_short')->nullable();
            $table->string('team_abbr', 8)->nullable();
            $table->string('league')->nullable();
            $table->string('status')->default('active');
            $table->unsignedSmallInteger('founded_year')->nullable();
            $table->string('home_city')->nullable();
            $table->string('primary_color', 7)->nullable();
            $table->string('secondary_color', 7)->nullable();
            $table->string('accent_color', 7)->nullable();
            $table->json('branding')->nullable();
            $table->json('uniforms')->nullable();
            $table->json('descriptions')->nullable();
            $table->json('contacts')->nullable();
            $table->json('social')->nullable();
            $table->json('season_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
