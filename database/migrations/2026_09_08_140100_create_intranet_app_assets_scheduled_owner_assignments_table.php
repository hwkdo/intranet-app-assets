<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('intranet_app_assets_scheduled_owner_assignments')) {
            return;
        }

        Schema::create('intranet_app_assets_scheduled_owner_assignments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('asset_id');
            $table->unsignedBigInteger('new_user_id');
            $table->timestamp('execute_at');
            $table->unsignedBigInteger('flow_id')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->foreign('asset_id', 'iaa_soa_asset_fk')
                ->references('id')
                ->on('intranet_app_assets_assets')
                ->cascadeOnDelete();
            $table->foreign('new_user_id', 'iaa_soa_new_user_fk')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->index(['execute_at', 'processed_at'], 'iaa_soa_execute_unprocessed_idx');
            $table->index('asset_id', 'iaa_soa_asset_idx');
            $table->index('new_user_id', 'iaa_soa_new_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intranet_app_assets_scheduled_owner_assignments');
    }
};
