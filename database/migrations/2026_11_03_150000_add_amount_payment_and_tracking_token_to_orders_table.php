<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->uuid('tracking_token')->nullable()->after('id');
            $table->decimal('amount', 10, 2)->nullable()->after('status');
            $table->timestamp('paid_at')->nullable()->after('cancelled_at');
        });

        // Orders received before this migration get their token too.
        DB::table('orders')->whereNull('tracking_token')->orderBy('id')->each(function (object $order): void {
            DB::table('orders')->where('id', $order->id)->update(['tracking_token' => (string) Str::uuid7()]);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->uuid('tracking_token')->nullable(false)->change();
            $table->unique('tracking_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['tracking_token']);
            $table->dropColumn(['tracking_token', 'amount', 'paid_at']);
        });
    }
};
