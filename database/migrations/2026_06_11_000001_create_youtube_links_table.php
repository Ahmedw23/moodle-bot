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
        Schema::create('youtube_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')
                ->nullable()
                ->constrained('processed_assignments')
                ->nullOnDelete();
            $table->string('url_hash')->unique();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('youtube_links');
    }
};
