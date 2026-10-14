<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Enums;

enum AssetItexiaRoomSyncStatus: string
{
    case Applied = 'applied';
    case AlreadySet = 'already_set';
    case NotFound = 'not_found';
    case Unavailable = 'unavailable';
    case ApiError = 'api_error';
    case MissingUuid = 'missing_uuid';
    case NoHint = 'no_hint';
    case NoRoomMatch = 'no_room_match';
    case Ambiguous = 'ambiguous';
    case PatchFailed = 'patch_failed';
}
