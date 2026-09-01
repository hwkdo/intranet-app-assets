<?php

namespace Hwkdo\IntranetAppAssets\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetHistory extends Model
{
    public const EventDeleted = 'deleted';

    public const EventRestored = 'restored';

    public const EventUpdated = 'updated';

    public const EventItexiaInventoryMailSent = 'itexia_inventory_mail_sent';

    public const EventItexiaInventoryMailFailed = 'itexia_inventory_mail_failed';

    public const EventItexiaNotFoundOnDelete = 'itexia_not_found_on_delete';

    public const EventItexiaSeventhingsUnavailableOnDelete = 'itexia_seventhings_unavailable_on_delete';

    public const EventInvoiceAutoResolveExhausted = 'invoice_auto_resolve_exhausted';

    public const EventHandoverRejectedByRecipient = 'handover_rejected_by_recipient';

    public const EventHandoverRejectionAdminAcknowledged = 'handover_rejection_admin_acknowledged';

    public const EventHandoverRejectionAdminResolvedNewOwner = 'handover_rejection_resolved_new_owner';

    public const EventHandoverRejectionAdminResolvedLocation = 'handover_rejection_resolved_location';

    public const EventHandoverRejectionAdminResolvedMissing = 'handover_rejection_resolved_missing';

    public const EventOwnerRequestedClarification = 'owner_requested_clarification';

    public const EventClarificationAdminResolvedCleared = 'clarification_admin_resolved_cleared';

    public const EventClarificationAdminResolvedNewOwner = 'clarification_admin_resolved_new_owner';

    public const EventClarificationAdminResolvedLocation = 'clarification_admin_resolved_location';

    public const EventClarificationAdminResolvedMissing = 'clarification_admin_resolved_missing';

    public const EventReturnInitiatedByHolder = 'return_initiated_by_holder';

    public const EventReturnCompletedByAdmin = 'return_completed_by_admin';

    public const EventHandoverConfirmedStatusCleared = 'handover_confirmed_status_cleared';

    public const EventPendingHandoverAdminAcknowledged = 'pending_handover_admin_acknowledged';

    public const EventPendingHandoverAdminResolvedNewOwner = 'pending_handover_admin_resolved_new_owner';

    public const EventPendingHandoverAdminResolvedLocation = 'pending_handover_admin_resolved_location';

    public const EventPendingHandoverAdminResolvedMissing = 'pending_handover_admin_resolved_missing';

    public const EventMissingAdminResolvedClearOnly = 'missing_admin_resolved_clear_only';

    public const EventMissingAdminResolvedNewOwner = 'missing_admin_resolved_new_owner';

    public const EventMissingAdminResolvedLocation = 'missing_admin_resolved_location';

    protected $table = 'intranet_app_assets_asset_histories';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
