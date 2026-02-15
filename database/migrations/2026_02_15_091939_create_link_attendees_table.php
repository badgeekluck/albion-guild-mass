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
        Schema::create('link_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shared_link_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained();
            $table->string('main_role');
            $table->string('second_role')->nullable();
            $table->string('third_role')->nullable();
            $table->integer('slot_index')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('link_attendees');
    }
};
