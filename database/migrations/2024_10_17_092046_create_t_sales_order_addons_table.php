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
        Schema::create('t_sales_order_addons', function (Blueprint $table) {
            $table->id();
            $table->integer('sales_order_id');
            $table->integer('company_id');
            $table->string('name');
            $table->float('amount');
            $table->float('tax');
            $table->string('hsn');
            $table->float('cgst');
            $table->float('sgst');
            $table->float('igst');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_sales_order_addons');
    }
};
