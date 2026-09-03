<?php

namespace Hwkdo\IntranetAppAssets\Livewire\Apps\Assets;

use App\Models\User;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetReturn;
use Hwkdo\IntranetAppAssets\Models\Handover;
use Hwkdo\IntranetAppAssets\Services\AssetClarificationAdminResolutionService;
use Hwkdo\IntranetAppAssets\Services\AssetReturnAdminCompletionService;
use Hwkdo\IntranetAppAssets\Services\HandoverRejectionAdminResolutionService;
use Hwkdo\IntranetAppAssets\Services\OpenHandoverAdminResolutionService;
use Hwkdo\IntranetAppAssets\Support\BulkAdminWorkflowSession;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Mehrfachaktion prüfen')]
class AdminBulkWorkflowReview extends Component
{
    /** @var array{admin_user_id: int, flow: string, ids: list<int>, payload: array<string, mixed>}|null */
    public ?array $workflow = null;

    public function mount(): void
    {
        $this->authorize('manage-app-assets');
        $this->workflow = BulkAdminWorkflowSession::getValidated();
        if ($this->workflow === null) {
            session()->flash('error', 'Keine gültige Mehrfachaktion.');
            $this->redirect(route('apps.assets.admin.index'), navigate: true);
        }
    }

    public function backUrl(): string
    {
        if ($this->workflow === null) {
            return route('apps.assets.admin.index');
        }

        return match ($this->workflow['flow']) {
            BulkAdminWorkflowSession::FLOW_RETURN_COMPLETE => route('apps.assets.admin.returns.pending'),
            BulkAdminWorkflowSession::FLOW_CLARIFICATION => route('apps.assets.admin.clarifications'),
            BulkAdminWorkflowSession::FLOW_OPEN_HANDOVER => route('apps.assets.admin.handovers', ['filter' => 'open']),
            BulkAdminWorkflowSession::FLOW_REJECTED_HANDOVER => route('apps.assets.admin.handovers', ['filter' => 'rejected']),
            BulkAdminWorkflowSession::FLOW_RETURN_INITIATE => route('apps.assets.liste'),
            default => route('apps.assets.admin.index'),
        };
    }

    public function flowHeading(): string
    {
        if ($this->workflow === null) {
            return 'Mehrfachaktion';
        }

        return match ($this->workflow['flow']) {
            BulkAdminWorkflowSession::FLOW_RETURN_COMPLETE => 'Offene Rückgaben abschließen',
            BulkAdminWorkflowSession::FLOW_CLARIFICATION => 'Klärungen auflösen',
            BulkAdminWorkflowSession::FLOW_OPEN_HANDOVER => 'Offene Übergaben auflösen',
            BulkAdminWorkflowSession::FLOW_REJECTED_HANDOVER => 'Abgelehnte Übergaben auflösen',
            BulkAdminWorkflowSession::FLOW_RETURN_INITIATE => 'Rückgabe einleiten',
            default => 'Mehrfachaktion',
        };
    }

    public function resolutionSummary(): string
    {
        if ($this->workflow === null) {
            return '';
        }

        $p = $this->workflow['payload'];
        $resolution = (string) ($p['resolution'] ?? '');

        return match ($this->workflow['flow']) {
            BulkAdminWorkflowSession::FLOW_RETURN_COMPLETE => $this->labelReturnCompleteResolution($resolution),
            BulkAdminWorkflowSession::FLOW_CLARIFICATION => $this->labelClarificationResolution($resolution),
            BulkAdminWorkflowSession::FLOW_OPEN_HANDOVER => $this->labelOpenHandoverResolution($resolution),
            BulkAdminWorkflowSession::FLOW_REJECTED_HANDOVER => $this->labelRejectedHandoverResolution($resolution),
            BulkAdminWorkflowSession::FLOW_RETURN_INITIATE => 'Rückgabe für ausgewählte Assets einleiten',
            default => $resolution,
        };
    }

    /**
     * @return list<string>
     */
    public function extraSummaryLines(): array
    {
        if ($this->workflow === null) {
            return [];
        }

        $p = $this->workflow['payload'];
        $flow = $this->workflow['flow'];
        $lines = [];

        if ($flow === BulkAdminWorkflowSession::FLOW_RETURN_INITIATE) {
            $lines[] = 'Grund / Notiz: '.trim((string) ($p['bulk_reason'] ?? ''));

            return $lines;
        }

        $lines[] = 'Grund / Notiz: '.trim((string) ($p['bulk_reason'] ?? ''));

        $resolution = (string) ($p['resolution'] ?? '');

        if ($flow === BulkAdminWorkflowSession::FLOW_RETURN_COMPLETE) {
            if ($resolution === AssetReturnAdminCompletionService::ResolutionNewOwner) {
                $uid = isset($p['new_owner_user_id']) ? (int) $p['new_owner_user_id'] : 0;
                if ($uid > 0) {
                    $u = User::query()->find($uid);
                    $lines[] = 'Neuer Besitzer: '.($u?->name ?? 'Benutzer #'.$uid);
                }
            }
            if ($resolution === AssetReturnAdminCompletionService::ResolutionSetLocation) {
                $loc = trim((string) ($p['location'] ?? ''));
                if ($loc !== '') {
                    $lines[] = 'Standort: '.$loc;
                }
            }
        }

        if (in_array($flow, [
            BulkAdminWorkflowSession::FLOW_CLARIFICATION,
            BulkAdminWorkflowSession::FLOW_OPEN_HANDOVER,
            BulkAdminWorkflowSession::FLOW_REJECTED_HANDOVER,
        ], true)) {
            if ($resolution === AssetClarificationAdminResolutionService::ResolutionNewOwner
                || $resolution === OpenHandoverAdminResolutionService::ResolutionNewOwner
                || $resolution === HandoverRejectionAdminResolutionService::ResolutionNewOwner) {
                $uid = isset($p['new_owner_user_id']) ? (int) $p['new_owner_user_id'] : 0;
                if ($uid > 0) {
                    $u = User::query()->find($uid);
                    $lines[] = 'Neuer Besitzer: '.($u?->name ?? 'Benutzer #'.$uid);
                }
            }
            if ($resolution === AssetClarificationAdminResolutionService::ResolutionSetLocation
                || $resolution === OpenHandoverAdminResolutionService::ResolutionSetLocation
                || $resolution === HandoverRejectionAdminResolutionService::ResolutionSetLocation) {
                $loc = trim((string) ($p['location'] ?? ''));
                if ($loc !== '') {
                    $lines[] = 'Standort: '.$loc;
                }
                if ($flow === BulkAdminWorkflowSession::FLOW_CLARIFICATION && array_key_exists('mark_in_stock', $p)) {
                    $lines[] = 'Gerätetyp: '.((bool) $p['mark_in_stock'] ? 'Auf Lager (Pool)' : 'Gemeinschaftsgerät');
                }
            }
        }

        return $lines;
    }

    /**
     * @return list<array{primary: string, secondary: string}>
     */
    public function previewRows(): array
    {
        if ($this->workflow === null) {
            return [];
        }

        return match ($this->workflow['flow']) {
            BulkAdminWorkflowSession::FLOW_RETURN_COMPLETE => $this->previewReturnCompleteRows(),
            BulkAdminWorkflowSession::FLOW_CLARIFICATION => $this->previewClarificationRows(),
            BulkAdminWorkflowSession::FLOW_OPEN_HANDOVER => $this->previewOpenHandoverRows(),
            BulkAdminWorkflowSession::FLOW_REJECTED_HANDOVER => $this->previewRejectedHandoverRows(),
            BulkAdminWorkflowSession::FLOW_RETURN_INITIATE => $this->previewReturnInitiateRows(),
            default => [],
        };
    }

    public function render(): View
    {
        return view('intranet-app-assets::livewire.apps.assets.admin-bulk-workflow-review');
    }

    private function labelReturnCompleteResolution(string $resolution): string
    {
        return match ($resolution) {
            AssetReturnAdminCompletionService::ResolutionNewOwner => 'Neuen Besitzer zuweisen',
            AssetReturnAdminCompletionService::ResolutionSetLocation => 'Besitzer entfernen und Standort setzen',
            default => $resolution,
        };
    }

    private function labelClarificationResolution(string $resolution): string
    {
        return match ($resolution) {
            AssetClarificationAdminResolutionService::ResolutionClearOnly => 'Nur Klärung aufheben',
            AssetClarificationAdminResolutionService::ResolutionNewOwner => 'Neuen Besitzer zuweisen',
            AssetClarificationAdminResolutionService::ResolutionSetLocation => 'Besitzer entfernen und Standort setzen',
            AssetClarificationAdminResolutionService::ResolutionMarkMissing => 'Als vermisst markieren',
            default => $resolution,
        };
    }

    private function labelOpenHandoverResolution(string $resolution): string
    {
        return match ($resolution) {
            OpenHandoverAdminResolutionService::ResolutionNewOwner => 'Neuen Besitzer zuweisen',
            OpenHandoverAdminResolutionService::ResolutionSetLocation => 'Besitzer entfernen und Standort setzen',
            OpenHandoverAdminResolutionService::ResolutionMarkMissing => 'Als vermisst markieren',
            default => $resolution,
        };
    }

    private function labelRejectedHandoverResolution(string $resolution): string
    {
        return match ($resolution) {
            HandoverRejectionAdminResolutionService::ResolutionNewOwner => 'Neuen Besitzer zuweisen',
            HandoverRejectionAdminResolutionService::ResolutionSetLocation => 'Besitzer entfernen und Standort setzen',
            HandoverRejectionAdminResolutionService::ResolutionMarkMissing => 'Als vermisst markieren',
            default => $resolution,
        };
    }

    /**
     * @return list<array{primary: string, secondary: string}>
     */
    private function previewReturnCompleteRows(): array
    {
        $ids = $this->workflow['ids'];
        $returns = AssetReturn::query()
            ->whereIn('id', $ids)
            ->with(['handover.asset', 'handover.recipient'])
            ->get()
            ->keyBy('id');

        $rows = [];
        foreach ($ids as $id) {
            $ret = $returns->get($id);
            if ($ret === null) {
                $rows[] = ['primary' => 'Rückgabe #'.$id, 'secondary' => 'Nicht gefunden'];

                continue;
            }
            $asset = $ret->handover?->asset;
            $rows[] = [
                'primary' => $asset?->display_name ?? 'Asset',
                'secondary' => 'SN: '.($asset?->serial_number ?? '—').' · Rückgabe #'.$ret->id,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{primary: string, secondary: string}>
     */
    private function previewClarificationRows(): array
    {
        $ids = $this->workflow['ids'];
        $assets = Asset::query()
            ->whereIn('id', $ids)
            ->with(['type', 'vendor'])
            ->get()
            ->keyBy('id');

        $rows = [];
        foreach ($ids as $id) {
            $asset = $assets->get($id);
            if ($asset === null) {
                $rows[] = ['primary' => 'Asset #'.$id, 'secondary' => 'Nicht gefunden'];

                continue;
            }
            $rows[] = [
                'primary' => $asset->display_name,
                'secondary' => 'SN: '.($asset->serial_number ?? '—'),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{primary: string, secondary: string}>
     */
    private function previewOpenHandoverRows(): array
    {
        $ids = $this->workflow['ids'];
        $handovers = Handover::query()
            ->whereIn('id', $ids)
            ->with(['asset', 'recipient'])
            ->get()
            ->keyBy('id');

        $rows = [];
        foreach ($ids as $id) {
            $handover = $handovers->get($id);
            if ($handover === null) {
                $rows[] = ['primary' => 'Übergabe #'.$id, 'secondary' => 'Nicht gefunden'];

                continue;
            }
            $asset = $handover->asset;
            $suffix = '';
            if ($handover->confirmed_at !== null || $handover->rejected_at !== null) {
                $suffix = ' · Hinweis: Übergabe ist nicht mehr „offen“';
            }
            $rows[] = [
                'primary' => $asset?->display_name ?? 'Asset',
                'secondary' => 'SN: '.($asset?->serial_number ?? '—').' · an '.($handover->recipient?->name ?? '—').$suffix,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{primary: string, secondary: string}>
     */
    private function previewRejectedHandoverRows(): array
    {
        $ids = $this->workflow['ids'];
        $handovers = Handover::query()
            ->whereIn('id', $ids)
            ->with(['asset', 'recipient'])
            ->get()
            ->keyBy('id');

        $rows = [];
        foreach ($ids as $id) {
            $handover = $handovers->get($id);
            if ($handover === null) {
                $rows[] = ['primary' => 'Übergabe #'.$id, 'secondary' => 'Nicht gefunden'];

                continue;
            }
            $asset = $handover->asset;
            $rows[] = [
                'primary' => $asset?->display_name ?? 'Asset',
                'secondary' => 'SN: '.($asset?->serial_number ?? '—').' · abgelehnt am '.($handover->rejected_at?->format('d.m.Y H:i') ?? '—'),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{primary: string, secondary: string}>
     */
    private function previewReturnInitiateRows(): array
    {
        $ids = $this->workflow['ids'];
        $assets = Asset::query()
            ->whereIn('id', $ids)
            ->with(['type', 'vendor', 'owner'])
            ->get()
            ->keyBy('id');

        $rows = [];
        foreach ($ids as $id) {
            $asset = $assets->get($id);
            if ($asset === null) {
                $rows[] = ['primary' => 'Asset #'.$id, 'secondary' => 'Nicht gefunden'];

                continue;
            }
            $rows[] = [
                'primary' => $asset->display_name,
                'secondary' => 'SN: '.($asset->serial_number ?? '—').' · Besitzer: '.($asset->owner?->name ?? '—'),
            ];
        }

        return $rows;
    }
}
