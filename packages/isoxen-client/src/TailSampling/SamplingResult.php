<?php

namespace Isoxen\Client\TailSampling;

enum SamplingResult
{
    case Keep;
    case Drop;
    case Forward;
}
