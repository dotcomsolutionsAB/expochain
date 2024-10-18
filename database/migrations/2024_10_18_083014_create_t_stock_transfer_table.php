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
            $table->integer('transfer_id');
            $table->string('godown_from');
            $table->string('godown_to');
            $table->date('transfer_date');
            $table->string('status');
            $table->string('log_user');
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
