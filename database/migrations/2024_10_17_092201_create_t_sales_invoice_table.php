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
        Schema::create('t_sales_invoice', function (Blueprint $table) {
            $table->id();
            $table->integer('company_id');
            $table->integer('client_id');
            $table->string('name');
            $table->string('sales_invoice_no');
            $table->date('sales_invoice_date');
            $table->integer('sales_order_id');
            $table->date('sales_order_date');
            $table->integer('template');
            $table->integer('contact_person');
            $table->enum('cash', ['0', '1']);
            $table->integer('user');
            $table->float('cgst');
            $table->float('sgst');
            $table->float('igst');
            $table->float('total');            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_sales_invoice');
    }
};
