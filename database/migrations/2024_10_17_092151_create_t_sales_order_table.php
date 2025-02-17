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
        Schema::create('t_sales_order', function (Blueprint $table) {
            $table->id();
            $table->integer('company_id');
            $table->integer('client_id');
            $table->string('name');
            $table->string('sales_order_no');
            $table->date('sales_order_date');
            $table->string('ref_no');
            $table->integer('template');
            $table->integer('contact_person')->nullable();
            $table->enum('status', ['pending', 'partial', 'completed', 'short_closed']);
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
        Schema::dropIfExists('t_sales_order');
    }
};
