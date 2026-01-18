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
        Schema::table('t_sales_order', function (Blueprint $table) {
            $table->renameColumn('id', 'so_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('t_sales_order', function (Blueprint $table) {
            $table->renameColumn('so_id', 'id');
        });
    }
};
