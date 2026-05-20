<?php

namespace App\Services;

use App\Models\AnalyticsDaily;
use App\Models\AnalyticsGame;
use App\Models\AnalyticsPlayer;
use App\Models\AnalyticsRound;
use App\Models\Player;
use App\Models\Room;
use App\Models\Round;

class AnalyticsService
{
    // Track room_code -> analytics_game_id mapping during a game's lifetime
    // In-memory only; data is persisted to DB before rooms are cleaned up

    public function recordRoomCreated(Room $room): void
    {
        $daily = AnalyticsDaily::getOrCreateForDate(now()->toDateString());
        $daily->increment('rooms_created');
    }

    public function recordPlayerJoined(Player $player): void
    {
        $daily = AnalyticsDaily::getOrCreateForDate(now()->toDateString());
        $daily->increment('total_players_joined');
    }

    public function recordGameStarted(Room $room): void
    {
        $daily = AnalyticsDaily::getOrCreateForDate(now()->toDateString());
        $daily->increment('games_played');

        AnalyticsGame::create([
            'room_code' => $room->code,
            'language' => $room->language,
            'room_type' => $room->type,
            'player_count' => $room->players()->count(),
            'rounds_played' => 0,
            'rounds_per_game' => $room->rounds_per_game,
            'started_at' => now(),
        ]);
    }

    public function recordRoundCompleted(Room $room, Round $round): void
    {
        $daily = AnalyticsDaily::getOrCreateForDate(now()->toDateString());
        $daily->increment('rounds_played');

        // Update the winner counters
        match ($round->winner) {
            'crew' => $daily->increment('crew_wins'),
            'imposter' => $daily->increment('imposter_wins'),
            'tie' => $daily->increment('ties'),
            default => null,
        };

        if ($round->imposter_caught) {
            $daily->increment('imposters_caught');
        }

        // Find or create the analytics game
        $analyticsGame = AnalyticsGame::where('room_code', $room->code)
            ->latest()
            ->first();

        if ($analyticsGame) {
            $analyticsGame->increment('rounds_played');

            $imposterId = $round->imposter_id;
            $votesCount = $round->votes()->count();
            $playerCount = $room->players()->count();

            AnalyticsRound::create([
                'analytics_game_id' => $analyticsGame->id,
                'round_number' => $round->round_number,
                'real_word' => $round->real_word,
                'imposter_hint' => $round->imposter_hint,
                'winner' => $round->winner,
                'imposter_caught' => $round->imposter_caught,
                'player_count' => $playerCount,
                'votes_count' => $votesCount,
            ]);
        }

        // Update per-player analytics
        $this->updatePlayerRoundStats($room, $round);
    }

    public function recordGameCompleted(Room $room): void
    {
        $daily = AnalyticsDaily::getOrCreateForDate(now()->toDateString());
        $daily->increment('games_completed');

        $analyticsGame = AnalyticsGame::where('room_code', $room->code)
            ->latest()
            ->first();

        if ($analyticsGame && $analyticsGame->started_at) {
            $analyticsGame->update([
                'ended_at' => now(),
                'duration_seconds' => $analyticsGame->started_at->diffInSeconds(now()),
                'player_count' => $room->players()->count(),
            ]);
        }

        // Update per-player game stats
        $this->updatePlayerGameStats($room);
    }

    public function recordImposterFled(Room $room): void
    {
        $daily = AnalyticsDaily::getOrCreateForDate(now()->toDateString());
        $daily->increment('imposters_fled');
    }

    private function updatePlayerRoundStats(Room $room, Round $round): void
    {
        $imposterId = $round->imposter_id;

        foreach ($room->players as $player) {
            $analyticsPlayer = AnalyticsPlayer::getOrCreateForNickname($player->nickname);

            if ($player->id === $imposterId) {
                $analyticsPlayer->increment('rounds_as_imposter');
                if ($round->imposter_caught) {
                    $analyticsPlayer->increment('times_caught_as_imposter');
                }
            } else {
                $analyticsPlayer->increment('rounds_as_crew');
            }
        }
    }

    private function updatePlayerGameStats(Room $room): void
    {
        // Determine game-level winner for each player
        $players = $room->players()->orderByDesc('score')->get();
        $rounds = $room->rounds;
        $topScore = $players->first()?->score ?? 0;

        foreach ($players as $player) {
            $analyticsPlayer = AnalyticsPlayer::getOrCreateForNickname($player->nickname);
            $analyticsPlayer->increment('games_played');

            // Count per-round wins by role
            foreach ($rounds as $round) {
                if ($round->imposter_id === $player->id) {
                    if ($round->winner === 'imposter') {
                        $analyticsPlayer->increment('wins_as_imposter');
                    }
                } else {
                    if ($round->winner === 'crew') {
                        $analyticsPlayer->increment('wins_as_crew');
                    }
                }
            }

            // Only count as game winner if player achieved the highest score
            if ($topScore > 0 && $player->score >= $topScore) {
                $analyticsPlayer->increment('games_won');
            }

            $analyticsPlayer->increment('total_score', $player->score);
        }
    }
}
