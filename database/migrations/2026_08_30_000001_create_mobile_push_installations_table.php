<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_push_installations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mobile_api_token_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();
            $table->string('provider', 20)->default('fcm');
            $table->string('identifier_kind', 20)->default('fid');
            $table->text('identifier');
            $table->string('identifier_hash', 64)->unique();
            $table->string('platform', 20)->default('android');
            $table->string('app_version', 50)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_push_installations');
    }
};
