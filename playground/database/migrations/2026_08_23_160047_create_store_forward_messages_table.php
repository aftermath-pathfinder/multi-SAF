<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_forward_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('message_id')->unique();
            $table->string('channel')->index();
            $table->string('transport')->nullable()->index();
            $table->string('key')->nullable();
            $table->json('payload');
            $table->json('headers')->nullable();
            $table->string('status', 20)->default('pending')->index(); // pending|processing|sent|failed|dead
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('available_at')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'channel', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_forward_messages');
    }
};
