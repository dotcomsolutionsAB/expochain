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
        Schema::create('t_assembly_operations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('assembly_operations_id');
            $table->date('assembly_operations_date');
            $table->integer('company_id');
            $table->enum('type', ['assemble', 'de-assemble']);
            $table->integer('product_id');
            $table->string('product_name');
            $table->integer('godown');
            $table->float('amount');
            $table->string('log_user');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_assembly_operations');
    }
};
