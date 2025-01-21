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
        Schema::create('t_counters', function (Blueprint $table) {
            $table->id();
            $table->integer('company_id');
            $table->string('name');
            $table->enum('type', ['manual', 'auto'])->default('manual'); // Type of counter
            $table->string('prefix')->nullable(); // Prefix for the counter
            $table->unsignedBigInteger('next_number')->default(1); // Next number in the sequence
            $table->string('postfix')->nullable(); // Postfix for the counter
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_counters');
    }
};
