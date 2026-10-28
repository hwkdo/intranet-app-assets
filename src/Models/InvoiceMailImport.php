<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Models;

use App\Models\User;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailImportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceMailImport extends Model
{
    protected $table = 'intranet_app_assets_invoice_mail_imports';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvoiceMailImportStatus::class,
            'received_from' => 'datetime',
            'received_to' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'found' => 'integer',
            'queued' => 'integer',
            'skipped_duplicates' => 'integer',
            'failed' => 'integer',
            'folders' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    /**
     * @return HasMany<InvoiceMail, $this>
     */
    public function mails(): HasMany
    {
        return $this->hasMany(InvoiceMail::class, 'import_id');
    }
}
