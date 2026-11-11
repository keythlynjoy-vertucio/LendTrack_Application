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
        Schema::create('borrowers', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('status', 16)->default('active'); // active|inactive
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['last_name', 'first_name']);
            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('borrowers');
    }
};
