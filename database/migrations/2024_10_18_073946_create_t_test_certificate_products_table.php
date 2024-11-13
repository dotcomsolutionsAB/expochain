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
        Schema::create('t_test_certificate_products', function (Blueprint $table) {
            $table->id();
            $table->integer('tc_id');
            $table->integer('company_id');
            $table->integer('product_id');
            $table->string('product_name');
            $table->integer('quantity');
            $table->string('sales_invoice_no');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_test_certificate_products');
    }
};
