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
        Schema::create('t_purchase_invoice', function (Blueprint $table) {
            $table->id();
            $table->integer('supplier_id');
            $table->string('name');
            $table->string('address_line_1');
            $table->string('address_line_2');
            $table->string('city');
            $table->string('pincode');
            $table->string('state');
            $table->string('country');
            $table->string('purchase_invoice_no');
            $table->date('purchase_invoice_date');
            $table->string('purchase_order_no');
            $table->float('cgst');
            $table->float('sgst');
            $table->float('igst');
            $table->string('currency');
            $table->integer('template');
            $table->integer('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_purchase_invoice');
    }
};
