<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('description')->nullable();
            $table->string('type')->default('percent');
            // Percent coupons store the percentage, fixed coupons store kuruş.
            $table->unsignedInteger('value');
            $table->unsignedInteger('min_total')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->nullable()->unique();
            $table->string('tracking_token', 64)->unique();
            $table->string('status')->default('pending_payment');

            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone');
            $table->string('identity_number', 11)->nullable();

            $table->boolean('requires_shipping')->default(true);
            $table->string('shipping_name')->nullable();
            $table->string('shipping_city')->nullable();
            $table->string('shipping_district')->nullable();
            $table->text('shipping_address')->nullable();
            $table->string('shipping_postal_code', 10)->nullable();

            $table->string('billing_type')->default('individual');
            $table->string('billing_name')->nullable();
            $table->string('billing_city')->nullable();
            $table->string('billing_district')->nullable();
            $table->text('billing_address')->nullable();
            $table->string('billing_company')->nullable();
            $table->string('billing_tax_office')->nullable();
            $table->string('billing_tax_number', 11)->nullable();

            $table->unsignedInteger('subtotal');
            $table->unsignedInteger('shipping_total')->default(0);
            $table->unsignedInteger('discount_total')->default(0);
            $table->unsignedInteger('total');
            $table->unsignedInteger('refunded_total')->default(0);
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->string('coupon_code')->nullable();

            $table->string('payment_token')->nullable()->index();
            $table->string('payment_id')->nullable();
            $table->unsignedSmallInteger('installment')->nullable();
            $table->string('card_family')->nullable();
            $table->string('card_association')->nullable();
            $table->string('card_last_four', 4)->nullable();
            $table->json('payment_response')->nullable();
            $table->string('payment_error')->nullable();
            $table->string('shipping_transaction_id')->nullable();
            $table->unsignedInteger('shipping_refunded')->default(0);
            $table->timestamp('paid_at')->nullable();

            $table->string('cargo_company')->nullable();
            $table->string('cargo_tracking_number')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->text('customer_note')->nullable();
            $table->text('admin_note')->nullable();

            $table->timestamp('terms_accepted_at')->nullable();
            $table->boolean('marketing_consent')->default(false);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->longText('contract_snapshot')->nullable();

            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();

            $table->timestamp('reserved_until')->nullable();
            $table->boolean('reservation_released')->default(false);
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('customer_email');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('workshop_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_type')->default('physical');
            $table->string('name');
            $table->string('variant_name')->nullable();
            $table->string('session_label')->nullable();
            $table->string('sku')->nullable();
            $table->unsignedInteger('unit_price');
            $table->unsignedSmallInteger('quantity');
            $table->unsignedInteger('line_total');
            // Line total after the order discount is spread across lines; this is what iyzico charges per item.
            $table->unsignedInteger('charged_total')->default(0);
            $table->unsignedInteger('refunded_total')->default(0);
            // Whether this line took units out of product/variant stock (made-to-order items do not).
            $table->boolean('stock_reserved')->default(false);
            $table->json('participants')->nullable();
            $table->string('payment_transaction_id')->nullable();
            $table->timestamps();
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('note')->nullable();
            $table->boolean('customer_notified')->default(false);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('order_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('amount');
            $table->string('type');
            $table->string('reason')->nullable();
            $table->string('provider_reference')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_refunds');
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('coupons');
    }
};
