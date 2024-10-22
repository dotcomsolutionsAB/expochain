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
        Schema::create('t_fabrication', function (Blueprint $table) {
            $table->id();
            $table->date('fabrication_date');
            $table->integer('product_id');
            $table->string('product_name');
            $table->enum('type', ['wastage', 'sample']);
            $table->integer('quantity');
            $table->integer('godown');
            $table->float('rate');
            $table->float('amount');
            // as it don't support `length`, it can store upto `65,535 characters for TEXT type in MySQL`
            $table->text('description');
            $table->string('log_user');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_fabrication');
    }
};
