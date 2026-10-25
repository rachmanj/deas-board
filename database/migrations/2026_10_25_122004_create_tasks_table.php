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
            $table->string('code')->unique();
            $table->string('title');
            $table->mediumText('description_md')->nullable();
            $table->string('status')->default('todo');
            $table->string('priority')->default('normal');
            $table->foreignId('assignee_session_id')->nullable()->constrained('sessions')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->date('start_date')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->string('source')->default('manual');
            $table->string('external_ref')->nullable();
            $table->string('created_by_type')->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'assignee_session_id']);
            $table->index('due_date');
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
