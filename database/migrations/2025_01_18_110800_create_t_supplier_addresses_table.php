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
        Schema::create('t_supplier_addresses', function (Blueprint $table) {
            $table->id();
            $table->integer('company_id')->unsigned();
            $table->enum('type', ['billing', 'shipping'])->default('billing'); // Billing or Shipping
            $table->integer('supplier_id')->unsigned();
            $table->string('country')->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('pincode')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_supplier_addresses');
    }
};
