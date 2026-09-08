<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intranet_app_assets_returns', function (Blueprint $table): void {
            if (! Schema::hasColumn('intranet_app_assets_returns', 'overdue_notify_user_id')) {
                $table->unsignedBigInteger('overdue_notify_user_id')->nullable()->after('last_overdue_reminder_sent_at');
                $table->index('overdue_notify_user_id', 'iaa_returns_overdue_notify_idx');
                $table->foreign('overdue_notify_user_id', 'iaa_ret_overdue_notify_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('intranet_app_assets_returns', 'austritt_cutoff_date')) {
                $table->date('austritt_cutoff_date')->nullable()->after('overdue_notify_user_id');
                $table->index('austritt_cutoff_date', 'iaa_returns_austritt_cutoff_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('intranet_app_assets_returns', function (Blueprint $table): void {
            if (Schema::hasColumn('intranet_app_assets_returns', 'overdue_notify_user_id')) {
                $table->dropForeign('iaa_ret_overdue_notify_fk');
                $table->dropIndex('iaa_returns_overdue_notify_idx');
                $table->dropColumn('overdue_notify_user_id');
            }

            if (Schema::hasColumn('intranet_app_assets_returns', 'austritt_cutoff_date')) {
                $table->dropIndex('iaa_returns_austritt_cutoff_idx');
                $table->dropColumn('austritt_cutoff_date');
            }
        });
    }
};
