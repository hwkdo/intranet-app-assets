<?php

namespace Hwkdo\IntranetAppAssets\Enums;

enum D3InvoiceAnalysisStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
