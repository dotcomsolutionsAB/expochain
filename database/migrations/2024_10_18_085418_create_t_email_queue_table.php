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
        Schema::create('t_email_queue', function (Blueprint $table) {
            $table->id();
            $table->integer('company_id');
            $table->string('to');
            $table->string('cc')->nullable();
            $table->string('bcc')->nullable();
            $table->string('from');
            $table->string('subject');
            // as it don't support `length`, it can store upto `65,535 characters for TEXT type in MySQL`
            $table->text('content');
            $table->string('attachment')->nullable();
            $table->text('response')->nullable();
            $table->enum('status', ['pending', 'sent', 'failed']);
            $table->string('log_user');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_email_queue');
    }
};
