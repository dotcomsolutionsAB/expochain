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
        Schema::create('t_purchase_return', function (Blueprint $table) {
            $table->id();
            $table->integer('company_id');
            $table->integer('supplier_id');
            $table->string('name');
            $table->string('purchase_return_no');
            $table->date('purchase_return_date');
            $table->string('purchase_invoice_no');
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
        Schema::dropIfExists('t_purchase_return');
    }
};
