<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intranet_app_assets_permanent_deletion_archives', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_asset_id')->index();
            $table->timestamp('archived_at')->useCurrent();
            $table->unsignedBigInteger('deleted_by_user_id')->nullable();
            $table->string('source', 64)->default('force_delete');
            $table->json('payload');
            $table->timestamps();

            $table->foreign('deleted_by_user_id', 'iaa_asset_perm_del_arch_del_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intranet_app_assets_permanent_deletion_archives');
    }
};
