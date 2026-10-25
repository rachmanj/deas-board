<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emails', function (Blueprint $table) {
            $table->id();
            $table->string('uid');
            $table->string('mailbox')->default('INBOX');
            $table->string('from_name')->nullable();
            $table->string('from_email')->nullable();
            $table->text('to')->nullable();
            $table->text('cc')->nullable();
            $table->text('subject')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->text('summary')->nullable();
            $table->boolean('has_full_body')->default(false);
            $table->longText('body_text')->nullable();
            $table->string('status')->default('baru');
            $table->timestamps();

            $table->unique(['mailbox', 'uid']);
            $table->index('received_at');
            $table->index('from_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emails');
    }
};
