<?php

declare(strict_types=1);

namespace MarekSkopal\MsMcpDocs\Dto;

final readonly class PkceChallengePair
{
    public function __construct(public string $codeVerifier, public string $codeChallenge,)
    {
    }
}
