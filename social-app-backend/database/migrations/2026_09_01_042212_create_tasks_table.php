<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->boolean('active')->default(true)->index();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            // Compound index for the list queries (filter by author + active, sorted by id DESC).
            $table->index(['author_id', 'active', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
