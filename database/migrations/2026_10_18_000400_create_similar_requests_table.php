<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('similar_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->text('message');
            $table->string('contact', 190);
            $table->string('contact_type', 10);
            $table->string('status', 20)->default('new')->index();
            $table->text('admin_note')->nullable();
            $table->foreignId('created_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('similar_requests');
    }
};
