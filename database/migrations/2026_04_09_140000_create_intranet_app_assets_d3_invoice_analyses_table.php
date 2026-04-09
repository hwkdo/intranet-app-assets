<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intranet_app_assets_d3_invoice_analyses', function (Blueprint $table) {
            $table->id();
            $table->string('d3_document_id')->unique();
            $table->string('status');
            $table->json('result_json')->nullable();
            $table->string('vision_model')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intranet_app_assets_d3_invoice_analyses');
    }
};
