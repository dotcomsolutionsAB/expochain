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
        Schema::table('t_purchase_invoice', function (Blueprint $table) {
            $table->unsignedBigInteger('po_id')->nullable()->after('lot_id');
            $table->foreign('po_id')->references('id')->on('t_purchase_order')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('t_purchase_invoice', function (Blueprint $table) {
            $table->dropForeign(['po_id']);
            $table->dropColumn('po_id');
        });
    }
};
