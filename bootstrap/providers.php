<?php

use App\Auth\Providers\FortifyServiceProvider;
use App\Core\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
];
