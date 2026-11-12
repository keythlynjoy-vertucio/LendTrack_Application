<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('loans', function (Blueprint $t) {
            $t->unsignedInteger('interest_months')->nullable();
            $t->json('schedule_dates')->nullable();
        });
        Schema::table('payments', fn (Blueprint $t) => $t->json('receipt_snapshot')->nullable());
        Schema::create('demo_replacements', function (Blueprint $t) {
            $t->id(); $t->string('version')->unique(); $t->json('backup'); $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('demo_replacements');
        Schema::table('payments', fn (Blueprint $t) => $t->dropColumn('receipt_snapshot'));
        Schema::table('loans', fn (Blueprint $t) => $t->dropColumn(['interest_months', 'schedule_dates']));
    }
};
