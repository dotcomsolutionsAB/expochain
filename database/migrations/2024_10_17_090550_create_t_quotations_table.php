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
        Schema::create('t_quotations', function (Blueprint $table) {
            $table->id();
            $table->integer('company_id');
            $table->integer('client_id');
            $table->string('name');
            $table->string('quotation_no');
            $table->string('quotation_date');
            $table->string('enquiry_no');
            $table->string('enquiry_date')->nullable();
            $table->integer('template');
            $table->integer('contact_person')->nullable();
            $table->integer('sales_person')->nullable();
            $table->enum('status', ['pending', 'rejected', 'completed'])->default('pending');
            $table->integer('user');
            $table->float('cgst');
            $table->float('sgst');
            $table->float('igst');
            $table->float('total');
            $table->string('currency');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_quotations');
    }
};
