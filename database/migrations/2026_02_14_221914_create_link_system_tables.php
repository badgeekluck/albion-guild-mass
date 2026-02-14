<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::create('shared_links', function (Blueprint $table) {
        $table->id();
        $table->foreignId('creator_id')->constrained('users');
        $table->string('slug', 10)->unique();
        $table->text('destination_url');
        $table->dateTime('expires_at');
        $table->timestamps();
    });

    // 2. Tıklayanları tutacak tablo
    Schema::create('link_clicks', function (Blueprint $table) {
        $table->id();
        $table->foreignId('shared_link_id')->constrained('shared_links')->onDelete('cascade');
        $table->foreignId('user_id')->constrained('users'); // Tıklayan kişi
        $table->timestamp('clicked_at');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('link_system_tables');
    }
};
