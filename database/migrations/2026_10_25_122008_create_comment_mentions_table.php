<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comment_mentions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_id')->constrained('task_comments')->cascadeOnDelete();
            $table->string('mentionable_type');
            $table->unsignedBigInteger('mentionable_id');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['mentionable_type', 'mentionable_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comment_mentions');
    }
};
