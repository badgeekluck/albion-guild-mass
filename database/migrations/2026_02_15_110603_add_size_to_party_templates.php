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
        Schema::table('party_templates', function (Blueprint $table) {
            $table->integer('size')->default(20)->after('name'); // Varsayılan 20
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('party_templates', function (Blueprint $table) {
            //
        });
    }
};
