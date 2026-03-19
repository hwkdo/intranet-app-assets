<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intranet_app_assets_assets', function (Blueprint $table) {
            $table->boolean('invoice_number_pending')->default(false)->after('invoice_number');
            $table->foreignId('created_by_user_id')->nullable()->after('invoice_number_pending')->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::table('intranet_app_assets_assets', function (Blueprint $table) {
            $table->dropForeign(['created_by_user_id']);
            $table->dropColumn(['invoice_number_pending', 'created_by_user_id']);
        });
    }
};
