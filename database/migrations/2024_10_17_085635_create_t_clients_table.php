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
        Schema::create('t_clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('customer_id');
            $table->integer('company_id');
            $table->string('mobile', 13);
            $table->string('email');
            $table->string('type');
            $table->string('category');
            $table->string('division');
            $table->string('plant');
            $table->string('gstin');
            $table->unsignedBigInteger('default_contact');
            $table->timestamps();

            // Add a composite unique index for name, gstin, and contact_id
            $table->unique(['name', 'gstin', 'company_id'], 'unique_name_gstin_company_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_clients');
    }
};
