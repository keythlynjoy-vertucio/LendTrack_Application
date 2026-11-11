<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('borrowers', function (Blueprint $table) {
            $table->string('province_code', 10)->nullable();
            $table->string('province_name')->nullable();

            $table->string('city_municipality_code', 10)->nullable();
            $table->string('city_municipality_name')->nullable();

            $table->string('barangay_code', 10)->nullable();
            $table->string('barangay_name')->nullable();

            $table->string('street_house')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('borrowers', function (Blueprint $table) {
            $table->dropColumn([
                'province_code',
                'province_name',
                'city_municipality_code',
                'city_municipality_name',
                'barangay_code',
                'barangay_name',
                'street_house',
            ]);
        });
    }
};