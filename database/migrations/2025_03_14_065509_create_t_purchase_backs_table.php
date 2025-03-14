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
        Schema::create('t_purchase_backs', function (Blueprint $table) {
            $table->id();
            $table->integer('company_id');
            $table->unsignedBigInteger('product'); // Foreign key reference to products
            $table->integer('quantity'); // Purchase back quantity
            $table->date('date'); // Date of purchase back
            $table->string('log_user'); // User who logged the purchase back
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_purchase_backs');
    }
};
