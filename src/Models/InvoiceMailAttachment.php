<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Models;

use App\Models\User;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailAttachmentStatus;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailD3Status;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailMatchStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class InvoiceMailAttachment extends Model
{
    public const CandidateMimeTypes = ['application/pdf', 'image/jpeg', 'image/png'];

    protected $table = 'intranet_app_assets_invoice_mail_attachments';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvoiceMailAttachmentStatus::class,
            'match_status' => InvoiceMailMatchStatus::class,
            'd3_status' => InvoiceMailD3Status::class,
            'agent_result' => 'array',
            'candidates' => 'array',
            'is_inline' => 'boolean',
            'size' => 'integer',
            'suggested_confidence' => 'float',
            'corrected_at' => 'datetime',
            'd3_uploaded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<InvoiceMail, $this>
     */
    public function invoiceMail(): BelongsTo
    {
        return $this->belongsTo(InvoiceMail::class, 'invoice_mail_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function suggestedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suggested_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function correctedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function correctedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function d3UploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'd3_uploaded_by');
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf'
            || str_ends_with(mb_strtolower($this->filename), '.pdf');
    }

    /**
     * Beleg-Kandidat: PDF oder nicht eingebettetes Bild (Logos/Signaturen fallen raus).
     */
    public function isCandidate(): bool
    {
        if ($this->isPdf()) {
            return true;
        }

        return ! $this->is_inline
            && in_array($this->mime_type, self::CandidateMimeTypes, true)
            && $this->size >= 20_000;
    }

    /**
     * Wem der Beleg zugeordnet wird: Korrektur vor Vorschlag.
     */
    public function effectiveUser(): ?User
    {
        return $this->correctedUser ?? $this->suggestedUser;
    }

    public function absolutePath(): ?string
    {
        if (! is_string($this->path) || $this->path === '') {
            return null;
        }

        return Storage::disk('local')->path($this->path);
    }

    public function wasCorrected(): bool
    {
        return $this->corrected_user_id !== null;
    }
}
