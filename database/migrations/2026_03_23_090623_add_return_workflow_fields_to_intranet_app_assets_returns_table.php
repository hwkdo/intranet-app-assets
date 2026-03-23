<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intranet_app_assets_returns', function (Blueprint $table): void {
            $table->foreignId('initiated_by_user_id')->nullable()->after('recipient_user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('received_confirmed_at')->nullable()->after('initiated_by_user_id');
            $table->timestamp('completed_at')->nullable()->after('received_confirmed_at');
        });

        DB::table('intranet_app_assets_returns')
            ->whereNotNull('recipient_user_id')
            ->whereNull('received_confirmed_at')
            ->update([
                'received_confirmed_at' => DB::raw('COALESCE(updated_at, created_at)'),
                'completed_at' => DB::raw('COALESCE(updated_at, created_at)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('intranet_app_assets_returns', function (Blueprint $table): void {
            $table->dropForeign(['initiated_by_user_id']);
            $table->dropColumn(['initiated_by_user_id', 'received_confirmed_at', 'completed_at']);
        });
    }
};
