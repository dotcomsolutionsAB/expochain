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
        Schema::create('t_assembly', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('assembly_id')->unique();
            $table->integer('company_id');
            $table->integer('product_id');
            $table->string('product_name');
            $table->integer('godown');
            $table->string('log_user');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_assembly');
    }
};
