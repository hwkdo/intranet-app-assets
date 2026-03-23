<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('intranet_app_assets_handovers', 'rejected_at')) {
            return;
        }

        Schema::table('intranet_app_assets_handovers', function (Blueprint $table): void {
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('intranet_app_assets_handovers', function (Blueprint $table): void {
            if (Schema::hasColumn('intranet_app_assets_handovers', 'rejected_at')) {
                $table->dropForeign(['rejected_by_user_id']);
                $table->dropColumn([
                    'rejected_at',
                    'rejected_by_user_id',
                ]);
            }
        });
    }
};
