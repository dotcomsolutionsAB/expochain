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
        Schema::create('t_debit_note', function (Blueprint $table) {
            $table->id();
            $table->integer('company_id');
            $table->integer('supplier_id');
            $table->string('name');
            $table->string('debit_note_no');
            $table->date('debit_note_date');
            $table->string('si_no')->nullable();
            $table->date('effective_date')->nullable();
            $table->string('type');
            // as it don't support `length`, it can store upto `65,535 characters for TEXT type in MySQL`
            $table->text('remarks');
            $table->float('cgst');
            $table->float('sgst');
            $table->float('igst');
            $table->float('total');
            $table->string('currency');
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
        Schema::dropIfExists('t_debit_note');
    }
};
