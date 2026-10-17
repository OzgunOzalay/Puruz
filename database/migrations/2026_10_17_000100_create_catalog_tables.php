<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('physical');
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('sku')->nullable()->unique();
            $table->string('short_description', 500)->nullable();
            $table->longText('description')->nullable();
            // Ordered list of image paths on the public disk; the first one is the cover.
            $table->json('images')->nullable();
            // All money values are stored in kuruş (1 TL = 100 kuruş), VAT included.
            $table->unsignedInteger('price');
            $table->unsignedInteger('compare_at_price')->nullable();
            $table->boolean('track_stock')->default(true);
            $table->integer('stock')->default(0);
            $table->boolean('made_to_order')->default(false);
            $table->unsignedSmallInteger('lead_time_days')->nullable();
            $table->string('materials')->nullable();
            $table->string('dimensions')->nullable();
            $table->text('care')->nullable();
            $table->unsignedInteger('weight_grams')->nullable();
            $table->string('status')->default('draft');
            $table->string('visibility')->default('public');
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 500)->nullable();
            $table->string('og_image')->nullable();
            $table->string('instagram_url')->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'visibility']);
        });

        Schema::create('category_product', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['category_id', 'product_id']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('sku')->nullable()->unique();
            $table->unsignedInteger('price')->nullable();
            $table->integer('stock')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('workshop_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('schedule_note')->nullable();
            $table->unsignedSmallInteger('capacity')->default(8);
            // Seats held by paid orders and by pending orders that are still inside their reservation window.
            $table->unsignedSmallInteger('seats_reserved')->default(0);
            $table->unsignedInteger('price')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->index(['product_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_sessions');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('category_product');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
    }
};
