<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intranet_app_assets_assets', function (Blueprint $table) {
            $table->id();
            $table->string('serial_number');
            $table->string('model');
            $table->foreignId('asset_type_id')->constrained('intranet_app_assets_asset_types');
            $table->foreignId('asset_vendor_id')->constrained('intranet_app_assets_asset_vendors');
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('name')->nullable();
            $table->string('location')->nullable();
            $table->boolean('is_clarification')->default(false);
            $table->boolean('is_missing')->default(false);
            $table->string('itexia_id')->nullable()->unique();
            $table->string('order_number')->nullable();
            $table->string('invoice_number')->nullable();
            $table->string('domain_connection')->nullable();
            $table->dateTime('domain_last_seen')->nullable();
            $table->dateTime('domain_last_checked')->nullable();
            $table->dateTime('last_logon')->nullable();
            $table->dateTime('last_logon_timestamp')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intranet_app_assets_assets');
    }
};
