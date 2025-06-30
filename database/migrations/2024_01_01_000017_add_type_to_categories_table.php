<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add type column to categories table
        Schema::table('categories', function (Blueprint $table) {
            $table->enum('type', ['equipment', 'service'])->default('equipment')->after('slug');
            $table->index('type');
        });

        // Update existing categories with appropriate types
        // Professional Services and its children should be marked as 'service'
        DB::table('categories')
            ->where('slug', 'professional-services')
            ->orWhere('name', 'Professional Services')
            ->update(['type' => 'service']);

        // Update all children of Professional Services category
        $serviceCategory = DB::table('categories')
            ->where('slug', 'professional-services')
            ->first();
            
        if ($serviceCategory) {
            DB::table('categories')
                ->where('parent_id', $serviceCategory->id)
                ->update(['type' => 'service']);
        }

        // All other categories remain as 'equipment' (the default)
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
};