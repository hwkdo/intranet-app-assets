<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Models;

use Hwkdo\IntranetAppAssets\Enums\InvoiceMailAttachmentStatus;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailEventLevel;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailMailboxAction;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailSource;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceMail extends Model
{
    protected $table = 'intranet_app_assets_invoice_mails';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => InvoiceMailSource::class,
            'status' => InvoiceMailStatus::class,
            'mailbox_action' => InvoiceMailMailboxAction::class,
            'to_recipients' => 'array',
            'relevance_payload' => 'array',
            'relevance_probability' => 'float',
            'threshold_used' => 'float',
            'is_relevant' => 'boolean',
            'report_mode' => 'boolean',
            'received_at' => 'datetime',
            'mailbox_action_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<InvoiceMailAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(InvoiceMailAttachment::class, 'invoice_mail_id');
    }

    /**
     * @return HasMany<InvoiceMailEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(InvoiceMailEvent::class, 'invoice_mail_id');
    }

    /**
     * @return BelongsTo<InvoiceMailImport, $this>
     */
    public function import(): BelongsTo
    {
        return $this->belongsTo(InvoiceMailImport::class, 'import_id');
    }

    public function plainBody(): string
    {
        $body = (string) $this->body;

        if ($this->body_type === 'html') {
            $body = (string) preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', ' ', $body);
            $body = (string) preg_replace('/<br\s*\/?>|<\/(p|div|tr|li|h\d)>/i', "\n", $body);
            $body = html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        $body = (string) preg_replace("/[ \t\x{00A0}]+/u", ' ', $body);

        return trim((string) preg_replace("/\n\s*\n+/", "\n\n", $body));
    }

    public function storageDirectory(): string
    {
        return 'assets/invoice-mails/'.$this->getKey();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function logEvent(
        string $step,
        string $message,
        InvoiceMailEventLevel $level = InvoiceMailEventLevel::Info,
        array $context = [],
        ?int $attachmentId = null,
        ?int $durationMs = null,
    ): InvoiceMailEvent {
        return $this->events()->create([
            'attachment_id' => $attachmentId,
            'step' => $step,
            'level' => $level,
            'message' => mb_substr($message, 0, 5000),
            'context' => $context === [] ? null : $context,
            'duration_ms' => $durationMs,
            'created_at' => now(),
        ]);
    }

    public function markStatus(InvoiceMailStatus $status, ?string $error = null): void
    {
        $this->update([
            'status' => $status,
            'last_error' => $error,
        ]);
    }

    /**
     * Status aus dem Stand der Anhänge neu berechnen, sobald keiner mehr in Arbeit ist.
     */
    public function refreshStatusFromAttachments(): void
    {
        $this->load('attachments');
        $candidates = $this->attachments->filter(fn (InvoiceMailAttachment $a): bool => $a->isCandidate());

        if ($candidates->contains(fn (InvoiceMailAttachment $a): bool => $a->status->isOpen())) {
            return;
        }

        $failed = $candidates->first(fn (InvoiceMailAttachment $a): bool => $a->status === InvoiceMailAttachmentStatus::Failed);

        $this->update([
            'status' => $failed ? InvoiceMailStatus::Failed : InvoiceMailStatus::Analyzed,
            'last_error' => $failed?->last_error,
            'processed_at' => now(),
        ]);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeNeedsAttention(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->where('status', InvoiceMailStatus::Failed)
                ->orWhereHas('attachments', fn (Builder $a) => $a->whereIn('match_status', ['ambiguous', 'none'])
                    ->whereNull('corrected_user_id')
                    ->where('d3_status', '!=', 'uploaded'));
        });
    }
}
