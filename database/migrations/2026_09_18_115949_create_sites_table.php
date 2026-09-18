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
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('niche');
            $table->string('domain')->unique();
            $table->string('tagline')->nullable();
            $table->string('locale')->default('pt_BR');
            $table->string('timezone')->default('America/Sao_Paulo');
            $table->string('theme')->default('default');
            $table->string('adsense_client_id')->nullable();
            $table->json('adsense_slots')->nullable();
            $table->unsignedInteger('publish_spacing_minutes')->default(45);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
