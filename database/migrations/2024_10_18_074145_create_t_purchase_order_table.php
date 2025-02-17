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
        Schema::create('t_purchase_order', function (Blueprint $table) {
            $table->id();
            $table->integer('company_id');
            $table->integer('supplier_id');
            $table->string('name');
            $table->string('purchase_order_no');
            $table->date('purchase_order_date')->nullable();
            $table->string('oa_no');
            $table->date('oa_date');
            $table->integer('template');
            $table->enum('status', ['pending', 'partial', 'completed', 'short_closed']);
            $table->integer('user');
            $table->float('cgst');
            $table->float('sgst');
            $table->float('igst');
            $table->float('total');
            $table->string('currency')->nullable();
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
        Schema::dropIfExists('t_purchase_order');
    }
};
