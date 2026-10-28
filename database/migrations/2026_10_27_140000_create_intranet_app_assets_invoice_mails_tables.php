<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intranet_app_assets_invoice_mail_imports', function (Blueprint $table) {
            $table->id();
            $table->string('mailbox_upn');
            $table->timestamp('received_from');
            $table->timestamp('received_to');
            $table->string('status')->default('queued');
            $table->unsignedInteger('found')->default(0);
            $table->unsignedInteger('queued')->default(0);
            $table->unsignedInteger('skipped_duplicates')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->text('last_error')->nullable();
            $table->foreignId('started_by')->nullable()->constrained('users', 'id', 'iaa_imi_started_by_fk')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('intranet_app_assets_invoice_mails', function (Blueprint $table) {
            $table->id();
            $table->string('graph_message_id', 512);
            $table->string('mailbox_upn');
            $table->string('internet_message_id', 512)->nullable();
            $table->string('source')->default('webhook');
            $table->foreignId('import_id')->nullable()->constrained('intranet_app_assets_invoice_mail_imports', 'id', 'iaa_im_import_fk')->nullOnDelete();
            $table->string('from_name')->nullable();
            $table->string('from_address')->nullable();
            $table->json('to_recipients')->nullable();
            $table->string('subject', 1000)->nullable();
            $table->timestamp('received_at')->nullable();
            $table->longText('body')->nullable();
            $table->string('body_type', 16)->default('text');
            $table->string('status')->default('received');
            $table->decimal('relevance_probability', 6, 5)->nullable();
            $table->string('relevance_kind')->nullable();
            $table->json('relevance_payload')->nullable();
            $table->boolean('is_relevant')->nullable();
            $table->decimal('threshold_used', 4, 3)->nullable();
            $table->string('irrelevant_reason')->nullable();
            $table->boolean('report_mode')->default(true);
            $table->string('mailbox_action')->nullable();
            $table->timestamp('mailbox_action_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['graph_message_id'], 'iaa_im_graph_message_uq');
            $table->index(['status', 'updated_at'], 'iaa_im_status_updated_idx');
            $table->index(['received_at'], 'iaa_im_received_idx');
            $table->index(['internet_message_id'], 'iaa_im_internet_msg_idx');
        });

        Schema::create('intranet_app_assets_invoice_mail_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_mail_id')->constrained('intranet_app_assets_invoice_mails', 'id', 'iaa_ima_mail_fk')->cascadeOnDelete();
            $table->string('graph_attachment_id', 512)->nullable();
            $table->string('filename');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->boolean('is_inline')->default(false);
            $table->string('path')->nullable();
            $table->string('sha256', 64)->nullable();
            $table->string('status')->default('pending');
            $table->string('document_kind')->nullable();
            $table->string('text_source')->nullable();
            $table->longText('extracted_text')->nullable();
            $table->json('agent_result')->nullable();
            $table->string('match_status')->nullable();
            $table->foreignId('suggested_user_id')->nullable()->constrained('users', 'id', 'iaa_ima_suggested_user_fk')->nullOnDelete();
            $table->decimal('suggested_confidence', 4, 3)->nullable();
            $table->json('candidates')->nullable();
            $table->foreignId('corrected_user_id')->nullable()->constrained('users', 'id', 'iaa_ima_corrected_user_fk')->nullOnDelete();
            $table->foreignId('corrected_by')->nullable()->constrained('users', 'id', 'iaa_ima_corrected_by_fk')->nullOnDelete();
            $table->timestamp('corrected_at')->nullable();
            $table->string('d3_status')->default('none');
            $table->string('d3_document_id')->nullable();
            $table->foreignId('d3_uploaded_by')->nullable()->constrained('users', 'id', 'iaa_ima_d3_uploaded_by_fk')->nullOnDelete();
            $table->timestamp('d3_uploaded_at')->nullable();
            $table->text('d3_error')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['sha256'], 'iaa_ima_sha256_idx');
            $table->index(['d3_status'], 'iaa_ima_d3_status_idx');
        });

        Schema::create('intranet_app_assets_invoice_mail_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_mail_id')->constrained('intranet_app_assets_invoice_mails', 'id', 'iaa_ime_mail_fk')->cascadeOnDelete();
            $table->foreignId('attachment_id')->nullable()->constrained('intranet_app_assets_invoice_mail_attachments', 'id', 'iaa_ime_attachment_fk')->cascadeOnDelete();
            $table->string('step');
            $table->string('level', 16)->default('info');
            $table->text('message');
            $table->json('context')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['invoice_mail_id', 'created_at'], 'iaa_ime_mail_created_idx');
            $table->index(['level', 'created_at'], 'iaa_ime_level_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intranet_app_assets_invoice_mail_events');
        Schema::dropIfExists('intranet_app_assets_invoice_mail_attachments');
        Schema::dropIfExists('intranet_app_assets_invoice_mails');
        Schema::dropIfExists('intranet_app_assets_invoice_mail_imports');
    }
};
