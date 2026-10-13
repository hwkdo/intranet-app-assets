<div>
    <flux:card class="overflow-hidden transition-colors hover:border-zinc-400/60 dark:hover:border-zinc-500/60">
        <flux:accordion exclusive transition>
            <flux:accordion.item>
                <flux:accordion.heading class="cursor-pointer select-none font-medium">
                    ConfigMgr/SCCM-Daten
                </flux:accordion.heading>
                <flux:accordion.content>
                    @if($this->configmgrSerialMismatches())
                        <flux:callout variant="warning" icon="exclamation-triangle" class="mb-4">
                            <flux:callout.heading>Seriennummer weicht ab</flux:callout.heading>
                            <flux:callout.text>Im Asset steht {{ $serialNumber }}, in SCCM {{ $configmgrSerialNumber }}.</flux:callout.text>
                        </flux:callout>
                    @endif
                    @if($configmgrError)
                        @if(filled($configmgrSerialNumber))
                            <dl class="mb-4 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                <dt class="font-semibold">Seriennummer (BIOS)</dt>
                                <dd class="font-mono text-xs">{{ $configmgrSerialNumber }}</dd>
                            </dl>
                        @endif
                        <flux:callout variant="warning" icon="exclamation-triangle">
                            <flux:callout.text>{{ $configmgrError }}</flux:callout.text>
                        </flux:callout>
                    @elseif($configmgrRows !== null && count($configmgrRows) > 0)
                        @php
                            $first = $configmgrRows[0];
                            $macAddresses = array_values(array_unique(array_filter(array_map(fn ($row) => $row->mac_adresse ?? null, $configmgrRows))));
                        @endphp
                        <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                            <dt class="font-semibold">Rechnername</dt>
                            <dd class="font-mono text-xs">{{ $first->rechnername ?? '—' }}</dd>
                            <dt class="font-semibold">SMBIOS-GUID</dt>
                            <dd class="font-mono text-xs">{{ $first->smbios_guid ?? '—' }}</dd>
                            <dt class="font-semibold">Letzter Logon-Benutzer</dt>
                            <dd>{{ $first->last_logon_user ?? '—' }}</dd>
                            <dt class="font-semibold">Seriennummer (BIOS)</dt>
                            <dd class="font-mono text-xs">{{ filled($configmgrSerialNumber) ? $configmgrSerialNumber : '—' }}</dd>
                            <dt class="font-semibold">MAC-Adresse(n)</dt>
                            <dd>
                                @if(count($macAddresses) > 0)
                                    <ul class="list-inside list-disc space-y-0.5 font-mono text-xs">
                                        @foreach($macAddresses as $mac)
                                            <li>{{ $mac }}</li>
                                        @endforeach
                                    </ul>
                                @else
                                    —
                                @endif
                            </dd>
                        </dl>
                    @elseif(filled($configmgrSerialNumber))
                        <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                            <dt class="font-semibold">Seriennummer (BIOS)</dt>
                            <dd class="font-mono text-xs">{{ $configmgrSerialNumber }}</dd>
                        </dl>
                    @else
                        <p class="text-sm text-zinc-500">Keine ConfigMgr-Daten für diesen Computernamen.</p>
                    @endif
                </flux:accordion.content>
            </flux:accordion.item>
        </flux:accordion>
    </flux:card>
</div>
