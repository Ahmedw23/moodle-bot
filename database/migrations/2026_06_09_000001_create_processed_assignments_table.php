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
        Schema::create('processed_assignments', function (Blueprint $table) {
            $table->id();
            $table->char('hash', 32)->unique();
            $table->string('title');
            $table->string('course_name')->nullable();
            $table->string('type', 32);
            $table->text('url')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('processed_assignments');
    }
};
