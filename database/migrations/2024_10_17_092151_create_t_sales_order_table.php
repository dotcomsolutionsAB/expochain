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
        Schema::create('t_sales_order', function (Blueprint $table) {
            $table->id();
            $table->integer('company_id');
            $table->integer('client_id');
            $table->integer('client_contact_id');
            $table->string('name');
            $table->string('address_line_1');
            $table->string('address_line_2');
            $table->string('city');
            $table->string('pincode');
            $table->string('state');
            $table->string('country');
            $table->string('sales_order_no');
            $table->date('sales_order_date');
            $table->integer('quotation_no');
            $table->float('cgst');
            $table->float('sgst');
            $table->float('igst');
            $table->float('total');
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
        Schema::dropIfExists('t_sales_order');
    }
};
