<?php

namespace App\Services;

use App\Models\PromoCode;
use App\Models\User;
use App\Models\UserPurchasedItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PromoCodeService
{
    public function __construct(
        private CreditService $creditService,
        private ShopService $shopService,
        private AvatarConfigService $avatarConfig,
    ) {}

    public function generateCode(string $rewardType, string $rewardId, ?int $maxUses = null, ?string $expiresAt = null): PromoCode
    {
        $this->validateReward($rewardType, $rewardId);

        $code = $this->generateHumanReadableCode();

        return PromoCode::create([
            'code' => $code,
            'reward_type' => $rewardType,
            'reward_id' => $rewardId,
            'max_uses' => $maxUses,
            'uses_count' => 0,
            'expires_at' => $expiresAt,
        ]);
    }

    public function redeem(User $user, string $code): array
    {
        return DB::transaction(function () use ($user, $code) {
            $promo = PromoCode::where('code', strtoupper(trim($code)))
                ->lockForUpdate()
                ->first();

            if (! $promo) {
                return ['success' => false, 'message' => __('promo.code_not_found')];
            }

            if ($promo->isExpired()) {
                return ['success' => false, 'message' => __('promo.code_expired')];
            }

            if ($promo->isFullyUsed()) {
                return ['success' => false, 'message' => __('promo.code_max_used')];
            }

            $alreadyRedeemed = DB::table('promo_code_redemptions')
                ->where('user_id', $user->id)
                ->where('promo_code_id', $promo->id)
                ->exists();

            if ($alreadyRedeemed) {
                return ['success' => false, 'message' => __('promo.code_already_redeemed')];
            }

            // Grant the reward
            $rewardDescription = $this->grantReward($user, $promo);

            // Record redemption
            DB::table('promo_code_redemptions')->insert([
                'user_id' => $user->id,
                'promo_code_id' => $promo->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $promo->increment('uses_count');

            return [
                'success' => true,
                'message' => __('promo.redeem_success', ['reward' => $rewardDescription]),
                'reward_type' => $promo->reward_type,
                'reward_description' => $rewardDescription,
            ];
        });
    }

    public function listCodes(): Collection
    {
        return PromoCode::orderByDesc('created_at')->get();
    }

    public function deleteCode(int $id): bool
    {
        $promo = PromoCode::find($id);
        if (! $promo) {
            return false;
        }

        DB::table('promo_code_redemptions')->where('promo_code_id', $id)->delete();
        $promo->delete();

        return true;
    }

    private function grantReward(User $user, PromoCode $promo): string
    {
        return match ($promo->reward_type) {
            'credits' => $this->grantCreditsReward($user, $promo),
            'element' => $this->grantElementReward($user, $promo),
            'costume' => $this->grantCostumeReward($user, $promo),
            default => 'Unknown reward',
        };
    }

    private function grantCreditsReward(User $user, PromoCode $promo): string
    {
        $amount = (int) $promo->reward_id;
        $this->creditService->earnCredits(
            $user,
            $amount,
            'promo_code',
            $promo->code,
            "Promo code: {$promo->code}"
        );

        return "{$amount} credits";
    }

    private function grantElementReward(User $user, PromoCode $promo): string
    {
        $filename = $promo->reward_id;

        if ($user->ownsAvatarItem($filename)) {
            // Already owned — grant credits equivalent instead
            $price = $this->avatarConfig->getItemPrice($filename) ?? 10;
            $this->creditService->earnCredits(
                $user,
                $price,
                'promo_code',
                $promo->code,
                "Promo code fallback (already owned): {$promo->code}"
            );

            return "{$price} credits (already owned item)";
        }

        UserPurchasedItem::create([
            'user_id' => $user->id,
            'item_type' => 'element',
            'item_id' => $filename,
            'layer' => $this->shopService->classifyLayer($filename),
            'price_paid' => 0,
            'purchased_at' => now(),
        ]);

        return $filename;
    }

    private function grantCostumeReward(User $user, PromoCode $promo): string
    {
        $costumeId = $promo->reward_id;
        $costume = $this->avatarConfig->getCostumeById($costumeId);

        if (! $costume) {
            return $this->grantCreditsReward($user, $promo);
        }

        if ($user->ownsCostume($costumeId)) {
            $price = $costume['price'] ?? 10;
            $this->creditService->earnCredits(
                $user,
                (int) $price,
                'promo_code',
                $promo->code,
                "Promo code fallback (already owned costume): {$promo->code}"
            );

            return "{$price} credits (already owned costume)";
        }

        UserPurchasedItem::create([
            'user_id' => $user->id,
            'item_type' => 'costume',
            'item_id' => $costumeId,
            'price_paid' => 0,
            'purchased_at' => now(),
        ]);

        return $costume['name'] ?? $costumeId;
    }

    private function validateReward(string $rewardType, string $rewardId): void
    {
        match ($rewardType) {
            'credits' => throw_if((int) $rewardId <= 0, new \Exception('Credit amount must be positive')),
            'element' => throw_if(! $this->avatarConfig->isPaidItem($rewardId), new \Exception("Item not found or not a paid item: {$rewardId}")),
            'costume' => throw_if(! $this->avatarConfig->getCostumeById($rewardId), new \Exception("Costume not found: {$rewardId}")),
            default => throw new \Exception("Invalid reward type: {$rewardType}"),
        };
    }

    private function generateHumanReadableCode(): string
    {
        $adjectives = ['WILD', 'FAST', 'FREE', 'GOLD', 'DARK', 'BOLD', 'LUCK', 'EPIC', 'RARE', 'COOL', 'STAR', 'MOON', 'FIRE', 'ICE', 'NEON'];
        $nouns = ['HAT', 'BOLT', 'COIN', 'GIFT', 'MASK', 'SPUR', 'CARD', 'RING', 'BOOT', 'GUN', 'ROPE', 'STAR', 'WOLF', 'HAWK', 'COYOTE'];

        do {
            $adj = $adjectives[array_rand($adjectives)];
            $noun = $nouns[array_rand($nouns)];
            $year = now()->format('Y');
            $code = "{$adj}-{$noun}-{$year}";
        } while (PromoCode::where('code', $code)->exists());

        return $code;
    }
}
