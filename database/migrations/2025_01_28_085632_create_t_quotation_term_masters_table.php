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
        Schema::create('t_quotation_term_masters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->integer('order');
            $table->string('name')->unique();
            $table->string('default_value')->nullable();
            $table->enum('type', ['textbox', 'dropdown']);
            $table->timestamps();

            $table->unique(['company_id', 'name'], 'unique_company_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_quotation_term_masters');
    }
};
