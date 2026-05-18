<?php

namespace App\Services;

use App\Models\CreditReward;
use App\Models\CreditTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreditService
{
    public function earnCredits(User $user, int $amount, string $type, ?string $reference = null, ?string $description = null): CreditTransaction
    {
        return DB::transaction(function () use ($user, $amount, $type, $reference, $description) {
            $user->lockForUpdate();
            $user->increment('credits', $amount);
            $user->refresh();

            return CreditTransaction::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'balance_after' => $user->credits,
                'type' => $type,
                'reference' => $reference,
                'description' => $description,
            ]);
        });
    }

    public function spendCredits(User $user, int $amount, string $type, ?string $reference = null, ?string $description = null): CreditTransaction
    {
        return DB::transaction(function () use ($user, $amount, $type, $reference, $description) {
            $user->lockForUpdate();

            if ($user->credits < $amount) {
                throw new \Exception(__('errors.insufficient_credits'));
            }

            $user->decrement('credits', $amount);
            $user->refresh();

            return CreditTransaction::create([
                'user_id' => $user->id,
                'amount' => -$amount,
                'balance_after' => $user->credits,
                'type' => $type,
                'reference' => $reference,
                'description' => $description,
            ]);
        });
    }

    public function grantCredits(User $admin, User $user, int $amount, ?string $description = null): CreditTransaction
    {
        return $this->earnCredits(
            $user,
            $amount,
            'admin_grant',
            null,
            $description ?? "Granted by {$admin->nickname}"
        );
    }

    public function rewardGameEvent(User $user, string $event): ?CreditTransaction
    {
        $reward = CreditReward::where('event', $event)->where('is_active', true)->first();

        if (! $reward) {
            return null;
        }

        return $this->earnCredits(
            $user,
            $reward->credits,
            'game_earned',
            $event,
            "Reward: {$event}"
        );
    }

    public function getBalance(User $user): int
    {
        return $user->credits;
    }

    public function getTransactions(User $user, int $limit = 50)
    {
        return $user->creditTransactions()->latest()->limit($limit)->get();
    }
}
