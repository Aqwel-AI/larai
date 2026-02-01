<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('larai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('method');
            $table->json('usage')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('larai_usage_logs');
    }
};
