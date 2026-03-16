<?php

namespace Hwkdo\IntranetAppAssets\Commands;

use Illuminate\Console\Command;

class IntranetAppAssetsCommand extends Command
{
    public $signature = 'intranet-app-assets';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
