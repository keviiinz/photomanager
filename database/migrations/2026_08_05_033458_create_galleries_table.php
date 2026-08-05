<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('galleries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('photographer_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('client_name');
            $table->string('slug')->unique();
            $table->string('unlock_code');
            $table->date('event_date')->nullable();
            $table->string('location')->nullable();
            $table->date('available_until')->nullable();
            $table->timestamps();

            $table->index('photographer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('galleries');
    }
};
