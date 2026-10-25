<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('task_comments')->nullOnDelete();
            $table->text('body_md');
            $table->string('author_type')->nullable();
            $table->unsignedBigInteger('author_id')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['task_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_comments');
    }
};
