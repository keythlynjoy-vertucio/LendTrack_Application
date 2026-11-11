<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('borrowers', function (Blueprint $table) {
            $table->string('region_code', 10)->nullable();
            $table->string('region_name')->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('borrowers', function (Blueprint $table) {
            $table->dropColumn(['region_code', 'region_name']);
        });
    }
};
