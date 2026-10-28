<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intranet_app_assets_invoice_mail_imports', function (Blueprint $table) {
            $table->json('folders')->nullable()->after('mailbox_upn');
        });
    }

    public function down(): void
    {
        Schema::table('intranet_app_assets_invoice_mail_imports', function (Blueprint $table) {
            $table->dropColumn('folders');
        });
    }
};
