<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->string('last_seen_ip')->nullable()->after('last_seen_at');
            $table->string('last_commit_ref', 64)->nullable()->after('last_seen_ip');
        });
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropColumn(['last_seen_ip', 'last_commit_ref']);
        });
    }
};
