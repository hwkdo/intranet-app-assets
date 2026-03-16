<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intranet_app_assets_seventhings_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('local_attribute');
            $table->string('itexia_attribute');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['local_attribute', 'itexia_attribute'], 'intranet_assets_seventhings_mapping_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intranet_app_assets_seventhings_mappings');
    }
};
