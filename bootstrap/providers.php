<?php

use App\Auth\Providers\FortifyServiceProvider;
use App\Core\Providers\AppServiceProvider;
use App\Watch\Providers\WatchServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    WatchServiceProvider::class,
];
