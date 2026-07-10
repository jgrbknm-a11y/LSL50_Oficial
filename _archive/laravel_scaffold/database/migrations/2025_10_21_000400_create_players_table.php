<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('document_id')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->integer('number')->nullable();
            $table->string('position')->nullable(); // C, 1B, 2B, SS, 3B, LF, CF, RF, P, DH
            $table->date('birthdate')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
        });
    }
    public function down(): void {
        Schema::dropIfExists('players');
    }
};
