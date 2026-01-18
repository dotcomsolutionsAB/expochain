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
        Schema::table('t_sales_order_products', function (Blueprint $table) {
            $table->renameColumn('sales_order_id', 'so_id');
        });

        Schema::table('t_sales_order_addons', function (Blueprint $table) {
            $table->renameColumn('sales_order_id', 'so_id');
        });

        Schema::table('t_sales_invoice', function (Blueprint $table) {
            $table->renameColumn('sales_order_id', 'so_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('t_sales_order_products', function (Blueprint $table) {
            $table->renameColumn('so_id', 'sales_order_id');
        });

        Schema::table('t_sales_order_addons', function (Blueprint $table) {
            $table->renameColumn('so_id', 'sales_order_id');
        });

        Schema::table('t_sales_invoice', function (Blueprint $table) {
            $table->renameColumn('so_id', 'sales_order_id');
        });
    }
};
