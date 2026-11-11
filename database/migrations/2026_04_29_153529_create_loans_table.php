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
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borrower_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            $table->string('reference_no')->unique();
            $table->decimal('principal_amount', 12, 2);
            $table->decimal('interest_rate', 5, 2); // percent
            $table->unsignedInteger('term_months');
            $table->date('start_date');
            $table->date('due_date');

            $table->decimal('total_payable', 12, 2);
            $table->decimal('total_paid', 12, 2)->default(0);

            $table->string('status', 16)->default('active'); // pending|active|paid|overdue|cancelled
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['status']);
            $table->index(['due_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
