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
        Schema::create('t_opening_stock', function (Blueprint $table) {
            $table->id();
            $table->integer('company_id');
            $table->integer('year');
            $table->integer('godown_id');
            $table->integer('quantity');
            $table->float('value');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_opening_stock');
    }
};
