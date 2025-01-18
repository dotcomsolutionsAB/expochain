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
        Schema::table('users', function (Blueprint $table) {
            //
            $table->string('mobile', 13)->after('email');
            $table->integer('otp')->after('mobile')->nullable();
            $table->timestamp('expires_at')->after('otp')->nullable();
            $table->enum('role', ['admin', 'user'])->after('expires_at');
            $table->integer('company_id')->after('role');
            $table->dropUnique('users_email_unique');
            $table->unique(['email', 'company_id'], 'unique_email_contact_id');
            $table->unique(['username', 'company_id'], 'users_username_company_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
