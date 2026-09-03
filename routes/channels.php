<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('assets-zentrale-channel', function ($user): bool {
    return $user->can('see-app-assets-zentrale');
});
