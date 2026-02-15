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
        Schema::create('party_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('structure');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::table('shared_links', function (Blueprint $table) {
            $table->json('template_snapshot')->nullable()->after('destination_url');
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('party_templates');
    }
};
