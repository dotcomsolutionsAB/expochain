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
            $table->integer('company_id');
            $table->integer('supplier_id');
            $table->string('name');
            $table->string('purchase_invoice_no');
            $table->date('purchase_invoice_date');
            $table->string('oa_no');
            $table->string('ref_no');
            $table->integer('template');
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
        Schema::dropIfExists('t_purchase_invoice');
    }
};
