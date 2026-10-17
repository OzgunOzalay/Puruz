<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Coupons are not used by the workshop; no live orders exist, so the columns are dropped outright.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn(['coupon_code', 'discount_total']);
        });
        Schema::dropIfExists('coupons');
    }

    public function down(): void
    {
        // Not reversible: the coupon feature was removed from the code.
    }
};
