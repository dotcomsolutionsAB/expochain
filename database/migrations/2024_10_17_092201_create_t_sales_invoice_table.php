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
            $table->string('sales_order_id')->nullable();
            $table->string('sales_order_no')->nullable();
            $table->date('sales_order_date')->nullable();
            $table->integer('template');
            $table->integer('sales_person')->nullable();
            $table->float('commission')->default(0);
            $table->enum('cash', ['0', '1']);
            $table->integer('user');
            $table->float('cgst');
            $table->float('sgst');
            $table->float('igst');
            $table->float('total');   
            $table->float('gross');
            $table->float('round_off');         
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
