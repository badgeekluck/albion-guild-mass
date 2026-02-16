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
        Schema::table('saved_builds', function (Blueprint $table) {
            $table->unsignedBigInteger('food_id')->nullable()->after('cape_id');
            $table->unsignedBigInteger('potion_id')->nullable()->after('food_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('saved_builds', function (Blueprint $table) {
            //
        });
    }
};
