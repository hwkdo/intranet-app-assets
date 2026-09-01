@props([
    'heading' => '',
    'subheading' => '',
    'navItems' => [],
    'renderAppIndexAuto' => null,
])

@php
    use Hwkdo\IntranetAppAssets\Support\AssetNavBadgeCounts;

    $clarificationNavBadge = null;
    $missingNavBadge = null;
    $pendingReturnsNavBadge = null;
    $openHandoversNavBadge = null;
    $rejectedHandoversNavBadge = null;

    if (auth()->user()?->can('manage-app-assets')) {
        $clarificationCount = AssetNavBadgeCounts::clarificationCount();
        $missingCount = AssetNavBadgeCounts::missingCount();
        $pendingReturnsCount = AssetNavBadgeCounts::pendingReturnsCount();
        $openHandoversCount = AssetNavBadgeCounts::openHandoversCount();
        $rejectedHandoversCount = AssetNavBadgeCounts::rejectedHandoversCount();

        if ($clarificationCount > 0) {
            $clarificationNavBadge = $clarificationCount;
        }

        if ($missingCount > 0) {
            $missingNavBadge = $missingCount;
        }

        if ($pendingReturnsCount > 0) {
            $pendingReturnsNavBadge = $pendingReturnsCount;
        }

        if ($openHandoversCount > 0) {
            $openHandoversNavBadge = $openHandoversCount;
        }

        if ($rejectedHandoversCount > 0) {
            $rejectedHandoversNavBadge = $rejectedHandoversCount;
        }
    }

    $defaultNavItems = [
        ['label' => 'Übersicht', 'href' => route('apps.assets.index'), 'icon' => 'home', 'description' => 'Zurück zur Übersicht', 'buttonText' => 'Übersicht anzeigen', 'welcomeSection' => 'main'],
        ['label' => 'Meine Assets', 'href' => route('apps.assets.meine-assets'), 'icon' => 'user', 'description' => 'Ihre zugewiesenen Assets', 'buttonText' => 'Meine Assets anzeigen', 'welcomeSection' => 'main'],
        ['label' => 'App-Info', 'href' => route('apps.assets.info'), 'icon' => 'information-circle', 'description' => 'Installierte Version und Release-Historie', 'buttonText' => 'App-Info anzeigen', 'welcomeSection' => 'settings'],
        ['type' => 'separator', 'label' => 'Tools', 'permission' => 'manage-app-assets'],
        ['label' => 'Suche', 'href' => route('apps.assets.search'), 'icon' => 'magnifying-glass', 'description' => 'Asset-Suche über Stammdaten, Verlauf und Notizen', 'buttonText' => 'Suche öffnen', 'permission' => 'manage-app-assets', 'welcomeSection' => 'main'],
        ['label' => 'Chat', 'href' => route('apps.assets.chat'), 'icon' => 'chat-bubble-left-right', 'description' => 'KI-Chat mit OpenWebUI und MCP-Server-Unterstützung', 'buttonText' => 'Chat öffnen', 'permission' => 'manage-app-assets', 'requiresAiUsage' => true, 'welcomeSection' => 'main'],
        ['type' => 'separator', 'label' => 'Ansichten', 'permission' => 'manage-app-assets'],
        ['label' => 'Alle Assets', 'href' => route('apps.assets.liste'), 'icon' => 'server-stack', 'description' => 'Alle Assets durchsuchen und verwalten', 'buttonText' => 'Assets anzeigen', 'permission' => 'manage-app-assets', 'welcomeSection' => 'main'],
        ['label' => 'Itexia-Geräte', 'href' => route('apps.assets.itexiageraete'), 'icon' => 'qr-code', 'description' => 'Assets mit Itexia-ID und Filter Gefunden/Nicht gefunden in Itexia', 'buttonText' => 'Itexia-Geräte anzeigen', 'permission' => 'manage-app-assets', 'welcomeSection' => 'main'],
        ['label' => 'Mobilgeräte', 'href' => route('apps.assets.mobilgeraete'), 'icon' => 'device-phone-mobile', 'description' => 'Mobilgeräte (is_intune_object) mit IMEI und Intune-Filter', 'buttonText' => 'Mobilgeräte anzeigen', 'permission' => 'manage-app-assets', 'welcomeSection' => 'main'],
        ['label' => 'Domänengeräte', 'href' => route('apps.assets.domaenengeraete'), 'icon' => 'computer-desktop', 'description' => 'Domänengeräte (is_domain_object) mit Domäne und Last Logon', 'buttonText' => 'Domänengeräte anzeigen', 'permission' => 'manage-app-assets', 'welcomeSection' => 'main'],
        ['type' => 'separator', 'label' => 'Rechnungen', 'permission' => 'manage-app-assets'],
        ['label' => 'Fehlende Rechnung', 'href' => route('apps.assets.fehlende-rechnung'), 'icon' => 'document-text', 'description' => 'Assets mit fehlender Rechnungsnummer (manuell oder nach Ende der automatischen BEN-Suche)', 'buttonText' => 'Fehlende Rechnung anzeigen', 'permission' => 'manage-app-assets', 'welcomeSection' => 'main'],
        ['label' => 'Rechnungen Analyse', 'href' => route('apps.assets.rechnungen'), 'icon' => 'banknotes', 'description' => 'D3-Rechnungsanalysen (Vision-Cache) einsehen und ausstehende Analysen starten', 'buttonText' => 'Rechnungen Analyse anzeigen', 'permission' => 'manage-app-assets', 'welcomeSection' => 'main'],
        ['type' => 'separator', 'label' => 'Admin', 'permission' => 'manage-app-assets'],
        ['label' => 'Yubikeys', 'href' => route('apps.assets.yubikeys'), 'icon' => 'key', 'description' => 'Aktive Benutzer und zugewiesene Yubikeys', 'buttonText' => 'Yubikeys anzeigen', 'permission' => 'manage-app-assets', 'welcomeSection' => 'main'],
        ['label' => 'Legacy-Assets', 'href' => route('apps.assets.legacy-assets'), 'icon' => 'circle-stack', 'description' => 'Legacy-Bestand vergleichen und fehlende Assets nachimportieren', 'buttonText' => 'Legacy-Assets anzeigen', 'permission' => 'manage-app-assets', 'welcomeSection' => 'main'],
        ['label' => 'Gelöschte Assets', 'href' => route('apps.assets.deleted'), 'icon' => 'archive-box', 'description' => 'Soft-gelöschte Assets einsehen und verwalten', 'buttonText' => 'Gelöschte Assets anzeigen', 'permission' => 'manage-app-assets', 'welcomeSection' => 'main'],
        ['label' => 'Unbestätigte Übergaben', 'href' => route('apps.assets.admin.open-handovers'), 'icon' => 'clock', 'description' => 'Noch nicht bestätigte Übergaben adminseitig bearbeiten', 'buttonText' => 'Unbestätigte Übergaben anzeigen', 'permission' => 'manage-app-assets', 'welcomeSection' => 'main', 'badge' => $openHandoversNavBadge, 'badgeColor' => 'red'],
        ['label' => 'Abgelehnte Übergaben', 'href' => route('apps.assets.admin.rejected-handovers'), 'icon' => 'x-circle', 'description' => 'Vom Empfänger abgelehnte Übergaben adminseitig auflösen', 'buttonText' => 'Abgelehnte Übergaben anzeigen', 'permission' => 'manage-app-assets', 'welcomeSection' => 'main', 'badge' => $rejectedHandoversNavBadge, 'badgeColor' => 'red'],
        ['label' => 'Offene Rückgaben', 'href' => route('apps.assets.admin.returns.pending'), 'icon' => 'arrow-uturn-left', 'description' => 'Rückgaben mit Empfangsbestätigung und Zuordnung abschließen', 'buttonText' => 'Rückgaben anzeigen', 'permission' => 'manage-app-assets', 'welcomeSection' => 'main', 'badge' => $pendingReturnsNavBadge, 'badgeColor' => 'red'],
        ['label' => 'Assets in Klärung', 'href' => route('apps.assets.admin.clarifications'), 'icon' => 'question-mark-circle', 'description' => 'Vom Besitzer gemeldete Klärungsfälle bearbeiten', 'buttonText' => 'Klärfälle anzeigen', 'permission' => 'manage-app-assets', 'welcomeSection' => 'main', 'badge' => $clarificationNavBadge, 'badgeColor' => 'red'],
        ['label' => 'Assets vermisst', 'href' => route('apps.assets.admin.missing'), 'icon' => 'exclamation-triangle', 'description' => 'Als vermisst markierte Assets einsehen und bearbeiten', 'buttonText' => 'Vermisste Assets anzeigen', 'permission' => 'manage-app-assets', 'welcomeSection' => 'main', 'badge' => $missingNavBadge, 'badgeColor' => 'red'],
        ['label' => 'Admin-Settings', 'href' => route('apps.assets.admin.index'), 'icon' => 'shield-check', 'description' => 'Typen, Hersteller und Seventhings-Zuordnung', 'buttonText' => 'Admin-Settings öffnen', 'permission' => 'manage-app-assets', 'welcomeSection' => 'settings'],
        
    ];

    $navItems = !empty($navItems) ? $navItems : $defaultNavItems;
    $customBgUrl = \Hwkdo\IntranetAppBase\Models\AppBackground::getCustomBackgroundUrl('assets');
@endphp

@if($customBgUrl)
    @push('app-styles')
    <style data-app-bg data-ts="{{ uniqid() }}">
        :root { --app-bg-image: url('{{ $customBgUrl }}'); }
    </style>
    @endpush
@endif

@php
    $shouldRenderAppIndexAuto = $renderAppIndexAuto ?? request()->routeIs('apps.assets.index');
@endphp

@if($shouldRenderAppIndexAuto)
    <x-intranet-app-base::app-layout
        app-identifier="assets"
        :heading="$heading"
        :subheading="$subheading"
        :nav-items="$navItems"
        :wrap-in-card="false"
    >
        <x-intranet-app-base::app-index-auto
            app-identifier="assets"
            app-name="Assets"
            app-description="Verwaltung von IT-Assets und Hardware"
            :nav-items="$navItems"
            welcome-title="Willkommen in der Asset-Verwaltung"
            welcome-description="Hier können Sie alle IT-Assets verwalten, Übergaben und Rückgaben nachverfolgen sowie die vollständige Asset-Historie einsehen."
        />
    </x-intranet-app-base::app-layout>
@else
    <x-intranet-app-base::app-layout
        app-identifier="assets"
        :heading="$heading"
        :subheading="$subheading"
        :nav-items="$navItems"
        :wrap-in-card="true"
    >
        {{ $slot }}
    </x-intranet-app-base::app-layout>
@endif
