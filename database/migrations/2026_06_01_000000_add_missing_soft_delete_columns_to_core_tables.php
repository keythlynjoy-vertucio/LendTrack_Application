<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('borrowers') && ! Schema::hasColumn('borrowers', 'deleted_at')) {
            Schema::table('borrowers', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (Schema::hasTable('loans') && ! Schema::hasColumn('loans', 'deleted_at')) {
            Schema::table('loans', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (Schema::hasTable('payments') && ! Schema::hasColumn('payments', 'deleted_at')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('borrowers') && Schema::hasColumn('borrowers', 'deleted_at')) {
            Schema::table('borrowers', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasTable('loans') && Schema::hasColumn('loans', 'deleted_at')) {
            Schema::table('loans', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'deleted_at')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
