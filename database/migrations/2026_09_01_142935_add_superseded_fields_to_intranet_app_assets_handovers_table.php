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
            $table->timestamp('superseded_at')->nullable()->after('rejected_by_user_id');
            $table->foreignId('superseded_by_user_id')->nullable()->after('superseded_at')->constrained('users')->nullOnDelete();
            $table->string('superseded_reason', 255)->nullable()->after('superseded_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('intranet_app_assets_handovers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('superseded_by_user_id');
            $table->dropColumn(['superseded_at', 'superseded_reason']);
        });
    }
};
