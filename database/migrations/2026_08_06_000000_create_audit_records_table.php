<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('govgr.audit.table', 'audit_records'), function (Blueprint $table) {
            $table->id();
            $table->dateTime('transaction_date');
            $table->string('protocol', 100)->nullable();
            $table->string('service', 50)->nullable();
            $table->string('environment', 20)->nullable();
            $table->string('endpoint')->nullable();
            $table->string('user_email', 100);
            $table->string('user_ip', 45)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->integer('status_code')->nullable();
            $table->text('exception')->nullable();
            $table->timestamps();

            $table->index(['service', 'transaction_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('govgr.audit.table', 'audit_records'));
    }
};
