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
        Schema::create('saved_builds', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('role_category')->nullable();

            $table->unsignedBigInteger('weapon_id')->nullable();
            $table->unsignedBigInteger('offhand_id')->nullable();
            $table->unsignedBigInteger('head_id')->nullable();
            $table->unsignedBigInteger('armor_id')->nullable();
            $table->unsignedBigInteger('shoe_id')->nullable();
            $table->unsignedBigInteger('cape_id')->nullable();

            $table->text('notes')->nullable(); // Yemek, pot vs.
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saved_builds');
    }
};
