<?php

namespace App\Console\Commands;

use App\Services\RoomCleanupService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:clean-inactive-rooms')]
#[Description('Delete inactive rooms and remove disconnected players')]
class CleanInactiveRooms extends Command
{
    public function __construct(
        private RoomCleanupService $cleanupService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->cleanupService->purgeStalePlayersFromAllRooms(broadcastGameEvents: true);

        $deleted = $this->cleanupService->removeInactiveRooms();

        if ($deleted > 0) {
            $this->info("Deleted {$deleted} inactive room(s)");
        }

        return self::SUCCESS;
    }
}
