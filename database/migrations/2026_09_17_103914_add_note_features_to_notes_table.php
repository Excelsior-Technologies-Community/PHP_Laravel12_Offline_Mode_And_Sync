<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->text('tags')->nullable()->after('content');
            $table->boolean('is_favorite')->default(false)->after('tags');
            $table->boolean('is_pinned')->default(false)->after('is_favorite');
            $table->timestamp('deleted_at')->nullable()->after('last_synced_at');

            $table->index('is_favorite');
            $table->index('is_pinned');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropIndex(['is_favorite']);
            $table->dropIndex(['is_pinned']);
            $table->dropIndex(['deleted_at']);

            $table->dropColumn([
                'tags',
                'is_favorite',
                'is_pinned',
                'deleted_at',
            ]);
        });
    }
};