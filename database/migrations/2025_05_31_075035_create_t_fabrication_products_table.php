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
        Schema::create('t_fabrication_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('fb_id');
            $table->unsignedBigInteger('product_id');
            $table->integer('quantity');
            $table->float('rate');
            $table->float('amount');
            $table->unsignedBigInteger('godown_id');
            $table->text('remarks')->nullable();
            $table->enum('type', ['raw', 'finished']);
            $table->float('wastage')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_fabrication_products');
    }
};
