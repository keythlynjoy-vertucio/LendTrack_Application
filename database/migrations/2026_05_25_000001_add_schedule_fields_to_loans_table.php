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
        Schema::table('loans', function (Blueprint $table) {
            $table->date('first_due_date')->nullable()->after('start_date');
            $table->decimal('penalty_per_missed_due', 12, 2)->default(0)->after('first_due_date');
            $table->text('notes')->nullable()->after('remarks');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn(['first_due_date', 'penalty_per_missed_due', 'notes']);
        });
    }
};
