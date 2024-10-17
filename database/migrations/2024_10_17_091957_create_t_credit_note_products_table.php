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
        Schema::create('t_credit_note_products', function (Blueprint $table) {
            $table->id();
            $table->integer('credit_note_id');
            $table->integer('product_id');
            $table->string('product_name');
            // as it don't support `length`, it can store upto `65,535 characters for TEXT type in MySQL`
            $table->text('description');
            $table->string('brand');
            $table->integer('quantity');
            $table->integer('unit');
            $table->float('price');
            $table->float('discount');
            $table->string('hsn');
            $table->float('tax');
            $table->float('cgst');
            $table->float('sqst');
            $table->float('igst');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_credit_note_products');
    }
};
