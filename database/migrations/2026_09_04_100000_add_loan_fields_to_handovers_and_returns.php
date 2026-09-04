<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intranet_app_assets_handovers', function (Blueprint $table): void {
            if (! Schema::hasColumn('intranet_app_assets_handovers', 'loan_due_at')) {
                $table->timestamp('loan_due_at')->nullable()->after('pending_confirmation_channel');
                $table->index('loan_due_at', 'iaa_ho_loan_due_idx');
            }
        });

        Schema::table('intranet_app_assets_returns', function (Blueprint $table): void {
            if (! Schema::hasColumn('intranet_app_assets_returns', 'source')) {
                $table->string('source', 32)->default('holder')->after('initiated_by_user_id');
                $table->index('source', 'iaa_ret_source_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('intranet_app_assets_returns', function (Blueprint $table): void {
            if (Schema::hasColumn('intranet_app_assets_returns', 'source')) {
                $table->dropIndex('iaa_ret_source_idx');
                $table->dropColumn('source');
            }
        });

        Schema::table('intranet_app_assets_handovers', function (Blueprint $table): void {
            if (Schema::hasColumn('intranet_app_assets_handovers', 'loan_due_at')) {
                $table->dropIndex('iaa_ho_loan_due_idx');
                $table->dropColumn('loan_due_at');
            }
        });
    }
};
