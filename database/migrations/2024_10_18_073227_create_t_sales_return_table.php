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
        Schema::create('t_sales_return', function (Blueprint $table) {
            $table->id();
            $table->integer('company_id');
            $table->integer('client_id');
            $table->string('name');
            $table->string('sales_return_no');
            $table->date('sales_return_date');
            $table->string('sales_invoice_no');
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
        Schema::dropIfExists('t_sales_return');
    }
};
