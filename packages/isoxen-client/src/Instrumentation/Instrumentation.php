<?php

namespace Isoxen\Client\Instrumentation;

interface Instrumentation
{
    public function register(array $options): void;
}
