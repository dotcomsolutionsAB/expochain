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
        Schema::create('t_customer_visits', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('customer');
            $table->string('location')->nullable();
            $table->string('contact_person_name')->nullable();
            $table->string('designation')->nullable();
            $table->string('mobile', 20)->nullable();
            $table->string('email')->nullable();
            $table->float('champion')->default(0);
            $table->float('fenner')->default(0);
            $table->longText('details')->nullable();
            $table->longText('growth')->nullable();
            $table->string('expense')->nullable();
            $table->float('amount_expense')->default(0);
            $table->string('upload')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_customer_visits');
    }
};
