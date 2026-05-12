<?php

namespace App\DTOs;

readonly class CleanupResult
{
    public function __construct(
        public bool $roomDeleted = false,
        public bool $creatorChanged = false,
        public int $removedPlayerCount = 0,
        public ?int $newCreatorId = null,
    ) {}
}
