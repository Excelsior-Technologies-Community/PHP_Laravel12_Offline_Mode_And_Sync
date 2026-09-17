<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_histories', function (Blueprint $table) {
            $table->id();

            $table->string('operation');
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('synced')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->unsignedInteger('conflicts')->default(0);
            $table->unsignedInteger('deleted')->default(0);

            $table->string('status')->default('completed');

            $table->text('error_message')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_histories');
    }
};