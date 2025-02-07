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
        Schema::create('t_sales_invoice_products', function (Blueprint $table) {
            $table->id();
            $table->integer('sales_invoice_id');
            $table->integer('company_id');
            $table->integer('product_id');
            $table->string('product_name');
            // as it don't support `length`, it can store upto `65,535 characters for TEXT type in MySQL`
            $table->text('description');
            $table->string('brand');
            $table->integer('quantity');
            $table->string('unit');
            $table->float('price');
            $table->integer('channel');
            $table->integer('returned');
            $table->float('profit');
            $table->integer('purchase_invoice_id');
            $table->float('purchase_rate');
            $table->enum('discount_type', ['percentage', 'value']);
            $table->float('discount');
            $table->integer('purchase_invoice_id');
            $table->integer('rate');
            $table->string('hsn');
            $table->float('tax');
            $table->float('cgst');
            $table->float('sgst');
            $table->float('igst');
            $table->string('godown');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_sales_invoice_products');
    }
};
