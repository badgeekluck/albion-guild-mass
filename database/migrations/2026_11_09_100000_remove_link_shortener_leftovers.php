<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The project started as a link shortener: link clicks, destination URLs and
 * stored Discord access tokens are no longer used anywhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('link_clicks');

        if (Schema::hasColumn('shared_links', 'destination_url')) {
            Schema::table('shared_links', function (Blueprint $table) {
                $table->dropColumn('destination_url');
            });
        }

        if (Schema::hasColumn('users', 'discord_token')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('discord_token');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('discord_token')->nullable()->after('avatar');
        });

        Schema::table('shared_links', function (Blueprint $table) {
            $table->text('destination_url')->nullable()->after('slug');
        });

        Schema::create('link_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shared_link_id')->constrained('shared_links')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->timestamp('clicked_at');
        });
    }
};
