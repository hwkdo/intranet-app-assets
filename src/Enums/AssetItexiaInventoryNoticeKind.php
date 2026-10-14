<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Enums;

enum AssetItexiaInventoryNoticeKind: string
{
    case Created = 'created';
    case LocationChanged = 'location_changed';
    case InvoiceAdded = 'invoice_added';
    case UuidLinked = 'uuid_linked';
}
