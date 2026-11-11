<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->uuid('client_request_id')->nullable()->unique();
            $table->char('client_request_hash', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['client_request_id']);

            $table->dropColumn([
                'client_request_id',
                'client_request_hash',
            ]);
        });
    }
};