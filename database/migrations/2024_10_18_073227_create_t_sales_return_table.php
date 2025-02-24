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
            $table->string('sales_invoice_id');
            // as it don't support `length`, it can store upto `65,535 characters for TEXT type in MySQL`
            $table->text('remarks')->nullable();
            $table->float('cgst');
            $table->float('sgst');
            $table->float('igst');
            $table->float('total');
            $table->string('currency');
            $table->integer('template');
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
        Schema::dropIfExists('t_sales_return');
    }
};
