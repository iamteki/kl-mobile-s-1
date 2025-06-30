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
        Schema::table('carts', function (Blueprint $table) {
            // Add status column
            $table->enum('status', ['active', 'abandoned', 'converted', 'expired'])
                  ->default('active')
                  ->after('customer_id');
            
            // Add user_id column (for authenticated users)
            $table->bigInteger('user_id')->unsigned()->nullable()->after('session_id');
            
            // Add indexes
            $table->index('status');
            $table->index('user_id');
            
            // Add foreign key constraint
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            // Add columns for cart summary
            $table->decimal('subtotal', 15, 2)->default(0.00)->after('special_requirements');
            $table->decimal('discount_amount', 15, 2)->default(0.00)->after('subtotal');
            $table->string('coupon_code', 50)->nullable()->after('discount_amount');
            $table->decimal('total_amount', 15, 2)->default(0.00)->after('coupon_code');
            
            // Add event date fields if missing
            $table->date('return_date')->nullable()->after('event_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            // Drop foreign key constraint first
            $table->dropForeign(['user_id']);
            
            // Drop indexes
            $table->dropIndex(['status']);
            $table->dropIndex(['user_id']);
            
            // Drop columns
            $table->dropColumn([
                'status',
                'user_id',
                'subtotal',
                'discount_amount',
                'coupon_code',
                'total_amount',
                'return_date'
            ]);
        });
    }
};