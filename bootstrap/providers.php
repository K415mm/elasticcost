<?php

use App\Providers\AppServiceProvider;
use App\Providers\FolioServiceProvider;
use App\Providers\HorizonServiceProvider;
use Phpkaiharness\PhpkaiharnessServiceProvider;

return [
    AppServiceProvider::class,
    FolioServiceProvider::class,
    HorizonServiceProvider::class,
    PhpkaiharnessServiceProvider::class,
];
