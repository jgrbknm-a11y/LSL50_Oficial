<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('team_name');
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('preferred_abbr', 8)->nullable();
            $table->string('home_city')->nullable();
            $table->json('branding_preferences')->nullable(); // colors, etc.
            $table->enum('status', ['pending','approved','rejected'])->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable(); // user id who approved
            $table->unsignedBigInteger('team_id')->nullable(); // created team id (when approved)
            $table->timestamps();

            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_registrations');
    }
};
