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
        Schema::table('action_plans', function (Blueprint $table) {
            if (!Schema::hasColumn('action_plans', 'period')) {
                $table->string('period', 7)->default('2026-08')->after('id');
            }
            if (!Schema::hasColumn('action_plans', 'pic_name')) {
                $table->string('pic_name', 100)->nullable()->after('owner_dept');
            }
            if (!Schema::hasColumn('action_plans', 'due_date')) {
                $table->date('due_date')->nullable()->after('pic_name');
            }
            if (!Schema::hasColumn('action_plans', 'priority')) {
                $table->enum('priority', ['High', 'Medium', 'Low'])->default('Medium')->after('due_date');
            }
            if (!Schema::hasColumn('action_plans', 'budget_estimate')) {
                $table->decimal('budget_estimate', 15, 2)->default(0)->after('priority');
            }
            if (!Schema::hasColumn('action_plans', 'actual_cost')) {
                $table->decimal('actual_cost', 15, 2)->default(0)->after('budget_estimate');
            }
            if (!Schema::hasColumn('action_plans', 'notes')) {
                $table->text('notes')->nullable()->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('action_plans', function (Blueprint $table) {
            $table->dropColumn([
                'period',
                'pic_name',
                'due_date',
                'priority',
                'budget_estimate',
                'actual_cost',
                'notes',
            ]);
        });
    }
};
