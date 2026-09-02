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
            $table->string('schedule_type', 32)->default('immediate')->after('initiated_by_user_id');
            $table->timestamp('scheduled_at')->nullable()->after('schedule_type');
            $table->timestamp('reminder1_sent_at')->nullable()->after('scheduled_at');
            $table->timestamp('reminder2_sent_at')->nullable()->after('reminder1_sent_at');
            $table->timestamp('last_overdue_reminder_sent_at')->nullable()->after('reminder2_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('intranet_app_assets_returns', function (Blueprint $table): void {
            $table->dropColumn([
                'schedule_type',
                'scheduled_at',
                'reminder1_sent_at',
                'reminder2_sent_at',
                'last_overdue_reminder_sent_at',
            ]);
        });
    }
};
