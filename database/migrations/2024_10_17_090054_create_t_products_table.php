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
        Schema::create('t_products', function (Blueprint $table) {
            $table->id();
            $table->integer('serial_number');
            $table->integer('company_id');
            $table->string('name')->unique();
            $table->string('alias');
            // as it don't support `length`, it can store upto `65,535 characters for TEXT type in MySQL`
            $table->text('description')->nullable();
            $table->string('type');
            $table->integer('group')->nullable();
            $table->integer('category')->nullable();
            $table->integer('sub_category')->nullable();
            $table->float('cost_price');
            $table->float('sale_price');
            $table->string('unit');
            $table->string('hsn');
            $table->float('tax');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_products');
    }
};
