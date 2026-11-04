<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deviation_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('category');
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->date('notified_on');
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['category', 'subject_type', 'subject_id', 'notified_on'], 'deviation_notifications_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deviation_notifications');
    }
};
