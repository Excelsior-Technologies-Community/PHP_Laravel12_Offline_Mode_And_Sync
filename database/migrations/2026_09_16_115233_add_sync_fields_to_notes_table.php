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
        Schema::table('notes', function (Blueprint $table) {
            $table->string('client_id')->nullable()->unique()->after('id');
            $table->unsignedInteger('sync_attempts')->default(0)->after('updated_at');
            $table->text('last_sync_error')->nullable()->after('sync_attempts');
            $table->timestamp('last_synced_at')->nullable()->after('last_sync_error');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropUnique(['client_id']);
            $table->dropColumn([
                'client_id',
                'sync_attempts',
                'last_sync_error',
                'last_synced_at',
            ]);
        });
    }
};