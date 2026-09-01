<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intranet_app_assets_assets', function (Blueprint $table): void {
            $table->boolean('is_in_stock')->default(false)->after('is_missing');
        });

        DB::table('intranet_app_assets_assets')
            ->where(function ($query): void {
                $query->whereNotNull('user_id')
                    ->orWhere('is_missing', true)
                    ->orWhere('is_clarification', true);
            })
            ->update(['is_in_stock' => false]);
    }

    public function down(): void
    {
        Schema::table('intranet_app_assets_assets', function (Blueprint $table): void {
            $table->dropColumn('is_in_stock');
        });
    }
};
