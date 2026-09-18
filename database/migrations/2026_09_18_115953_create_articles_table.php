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
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('news_source_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('prompt_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_lead_id')->nullable();
            $table->string('title');
            $table->string('slug');
            $table->text('excerpt')->nullable();
            $table->longText('content')->nullable();
            $table->string('source_reference_title')->nullable();
            $table->string('source_reference_url')->nullable();
            $table->string('featured_image_url')->nullable();
            $table->string('featured_image_credit_name')->nullable();
            $table->string('featured_image_credit_url')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->json('generation_meta')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'slug']);
            $table->unique(['site_id', 'external_lead_id']);
            $table->index(['status', 'scheduled_for']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
