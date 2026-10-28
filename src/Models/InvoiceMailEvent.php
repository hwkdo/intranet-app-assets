<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Models;

use Hwkdo\IntranetAppAssets\Enums\InvoiceMailEventLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceMailEvent extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'intranet_app_assets_invoice_mail_events';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => InvoiceMailEventLevel::class,
            'context' => 'array',
            'duration_ms' => 'integer',
            'created_at' => 'datetime',
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
     * @return BelongsTo<InvoiceMailAttachment, $this>
     */
    public function attachment(): BelongsTo
    {
        return $this->belongsTo(InvoiceMailAttachment::class, 'attachment_id');
    }
}
