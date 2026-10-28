<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Services\InvoiceMail;

use Hwkdo\IntranetAppAssets\Jobs\InvoiceMail\IngestInvoiceMailJob;
use Hwkdo\MsGraphLaravel\Models\GraphWebhookJobMapping;
use Hwkdo\MsGraphLaravel\Models\Subscription;
use Hwkdo\MsGraphLaravel\Services\SubscriptionService;

/**
 * Pflegt das Webhook-Mapping in ms-graph-laravel. Erneuerung übernimmt `ms-graph-laravel:check-subscriptions`.
 */
class InvoiceMailSubscriptionService
{
    public const WebhookType = 'assets_invoice_mail';

    public function resourceFor(string $upn): string
    {
        return "/users/{$upn}/mailFolders('inbox')/messages";
    }

    public function mapping(): ?GraphWebhookJobMapping
    {
        return GraphWebhookJobMapping::query()->where('webhook_type', self::WebhookType)->first();
    }

    /**
     * @return array{active: bool, mapping: bool, resource_matches: bool, expires_at: ?string}
     */
    public function status(string $upn): array
    {
        $mapping = $this->mapping();
        $subscription = $mapping !== null && $mapping->hasSubscriptionData()
            ? Subscription::query()
                ->where('resource', $mapping->resource)
                ->where('notificationUrl', $mapping->notification_url)
                ->latest()
                ->first()
            : null;

        return [
            'active' => (bool) $mapping?->is_active,
            'mapping' => $mapping !== null,
            'resource_matches' => $mapping?->resource === $this->resourceFor($upn),
            'expires_at' => $subscription?->expiration?->timezone(config('app.timezone'))->format('d.m.Y H:i'),
        ];
    }

    /**
     * @return array{ok: bool, status: string, message: string}
     */
    public function ensure(string $upn): array
    {
        $mapping = GraphWebhookJobMapping::query()->updateOrCreate(
            ['webhook_type' => self::WebhookType],
            [
                'job_class' => IngestInvoiceMailJob::class,
                'is_active' => true,
                'name' => 'Assets: Rechnungs-Mails',
                'description' => 'Neue Mails im Rechnungs-Sammelpostfach (intranet-app-assets).',
                'upn' => $upn,
                'resource' => $this->resourceFor($upn),
                'notification_url' => GraphWebhookJobMapping::generateNotificationUrl(self::WebhookType),
                'change_type' => 'created',
            ],
        );

        return app(SubscriptionService::class)->syncMapping($mapping, force: $mapping->wasChanged('resource'));
    }

    public function deactivate(): void
    {
        $this->mapping()?->update(['is_active' => false]);
    }
}
