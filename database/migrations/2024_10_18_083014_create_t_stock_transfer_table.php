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
        Schema::create('t_stock_transfer', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transfer_id')->unique();
            $table->integer('company_id');
            $table->integer('godown_from')->nullable();
            $table->integer('godown_to')->nullable();
            $table->date('transfer_date');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_stock_transfer');
    }
};
