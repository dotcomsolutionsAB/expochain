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
        Schema::create('t_purchase_order_products', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_order_id');
            $table->integer('company_id');
            $table->integer('product_id');
            $table->string('product_name');
            // as it don't support `length`, it can store upto `65,535 characters for TEXT type in MySQL`
            $table->text('description')->nullable();
            $table->integer('quantity');
            $table->string('unit');
            $table->float('price');
            $table->float('discount');
            $table->enum('discount_type', ['percentage', 'value']);
            $table->string('hsn');
            $table->float('tax');
            $table->float('cgst');
            $table->float('sgst');
            $table->float('igst');
            $table->float('amount')->default('0');
            $table->integer('channel')->default('0')->nullable();
            $table->integer('received')->default('0');
            $table->integer('short_closed')->default('0');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_purchase_order_products');
    }
};
