<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        foreach (['closed_at', 'closed_by', 'close_reason', 'close_remarks'] as $column) {
            if (Schema::hasColumn('loans', $column)) { continue; }
            Schema::table('loans', function (Blueprint $t) use ($column) {
                match ($column) {
                    'closed_at' => $t->timestamp($column)->nullable(),
                    'closed_by' => $t->unsignedBigInteger($column)->nullable(),
                    'close_remarks' => $t->text($column)->nullable(),
                    default => $t->string($column)->nullable(),
                };
            });
        }
    }
    public function down(): void {
        // Preserve closure audit metadata during rollback.
    }
};
