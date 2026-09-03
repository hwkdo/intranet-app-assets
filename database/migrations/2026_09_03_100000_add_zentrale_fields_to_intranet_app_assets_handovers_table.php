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
            if (! Schema::hasColumn('intranet_app_assets_handovers', 'pending_confirmation_channel')) {
                $table->string('pending_confirmation_channel', 40)->nullable();
                $table->index('pending_confirmation_channel', 'iaa_ho_pending_channel_idx');
            }

            if (! Schema::hasColumn('intranet_app_assets_handovers', 'confirmed_assisted_by_user_id')) {
                $table->foreignId('confirmed_assisted_by_user_id')
                    ->nullable()
                    ->constrained('users', 'id', 'iaa_ho_assisted_by_fk')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('intranet_app_assets_handovers', function (Blueprint $table): void {
            if (Schema::hasColumn('intranet_app_assets_handovers', 'confirmed_assisted_by_user_id')) {
                $table->dropForeign('iaa_ho_assisted_by_fk');
                $table->dropColumn('confirmed_assisted_by_user_id');
            }

            if (Schema::hasColumn('intranet_app_assets_handovers', 'pending_confirmation_channel')) {
                $table->dropIndex('iaa_ho_pending_channel_idx');
                $table->dropColumn('pending_confirmation_channel');
            }
        });
    }
};
