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
        Schema::create('t_suppliers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_id');
            $table->integer('company_id');
            $table->string('name');
            // $table->string('address_line_1');
            // $table->string('address_line_2');
            // $table->string('city');
            // $table->string('pincode');
            // $table->string('state');
            // $table->string('country');
            $table->string('gstin');
            $table->string('mobile', 13);
            $table->string('email');
            $table->unsignedBigInteger('default_contact');
            $table->timestamps();

            // Add a composite unique index for name, gstin, and contact_id
            $table->unique(['name', 'gstin', 'company_id'], 'unique_supplier_name_gstin_company_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_suppliers');
    }
};
