<?php

namespace Hwkdo\IntranetAppAssets\Livewire\Apps\Assets;

use Hwkdo\IntranetAppAssets\Services\BulkAdminWorkflowExecutor;
use Hwkdo\IntranetAppAssets\Support\BulkAdminWorkflowSession;
use Hwkdo\IntranetAppAssets\Support\BulkSelectionUi;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Mehrfachaktion ausführen')]
class AdminBulkWorkflowCommit extends Component
{
    public function mount(): void
    {
        $this->authorize('manage-app-assets');

        $workflow = BulkAdminWorkflowSession::getValidated();
        if ($workflow === null) {
            session()->flash('error', 'Keine gültige Mehrfachaktion.');
            $this->redirectAfterBulk(route('apps.assets.admin.index'));

            return;
        }

        BulkAdminWorkflowSession::forget();

        $redirectUrl = $this->redirectUrlForFlow($workflow['flow']);

        try {
            $result = app(BulkAdminWorkflowExecutor::class)->execute($workflow);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
            $this->redirectAfterBulk($redirectUrl);

            return;
        } catch (\Throwable $e) {
            report($e);
            session()->flash('error', 'Die Mehrfachaktion ist fehlgeschlagen.');
            $this->redirectAfterBulk($redirectUrl);

            return;
        }

        if ($result['processed'] > 0) {
            BulkAdminWorkflowSession::forgetSelectionAfterBulkSuccess($workflow['flow']);
        }

        session()->flash(
            'message',
            "Mehrfachaktion abgeschlossen: {$result['processed']} verarbeitet, {$result['failed']} nicht verarbeitet.",
        );

        $this->redirectAfterBulk($redirectUrl);
    }

    private function redirectAfterBulk(string $url): void
    {
        $this->js(BulkSelectionUi::livewireClearSelectionJs());
        $this->redirect($url, navigate: true);
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('intranet-app-assets::livewire.apps.assets.admin-bulk-workflow-commit');
    }

    private function redirectUrlForFlow(string $flow): string
    {
        return match ($flow) {
            BulkAdminWorkflowSession::FLOW_RETURN_COMPLETE => route('apps.assets.admin.returns.pending'),
            BulkAdminWorkflowSession::FLOW_CLARIFICATION => route('apps.assets.admin.clarifications'),
            BulkAdminWorkflowSession::FLOW_OPEN_HANDOVER => route('apps.assets.admin.open-handovers'),
            BulkAdminWorkflowSession::FLOW_REJECTED_HANDOVER => route('apps.assets.admin.rejected-handovers'),
            BulkAdminWorkflowSession::FLOW_RETURN_INITIATE => route('apps.assets.liste'),
            default => route('apps.assets.admin.index'),
        };
    }
}
