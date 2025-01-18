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
        Schema::create('t_client_addresses', function (Blueprint $table) {
            $table->id();
            $table->integer('company_id')->unsigned();
            $table->enum('type', ['billing', 'shipping'])->default('billing'); // Billing or Shipping
            $table->integer('client_id')->unsigned();
            $table->string('country');
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('city');
            $table->string('state');
            $table->string('pincode');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_client_addresses');
    }
};
