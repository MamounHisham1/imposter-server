<?php

namespace Database\Seeders;

use App\Models\CreditReward;
use Illuminate\Database\Seeder;

class CreditRewardSeeder extends Seeder
{
    public function run(): void
    {
        $rewards = [
            ['event' => 'game_played', 'credits' => 5, 'is_active' => true],
            ['event' => 'win_as_crew', 'credits' => 10, 'is_active' => true],
            ['event' => 'win_as_imposter', 'credits' => 15, 'is_active' => true],
            ['event' => 'correct_vote', 'credits' => 5, 'is_active' => true],
        ];

        foreach ($rewards as $reward) {
            CreditReward::updateOrCreate(
                ['event' => $reward['event']],
                $reward
            );
        }
    }
}
