<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserPurchasedItem;

class ShopService
{
    public function __construct(
        private CreditService $creditService,
        private AvatarConfigService $avatarConfig,
    ) {}

    public function getShopItems(): array
    {
        return $this->avatarConfig->getAllShopItems();
    }

    public function getUserInventory(User $user): array
    {
        $items = $user->purchasedItems()->get();

        $elements = $items->where('item_type', 'element')->pluck('item_id')->toArray();
        $costumes = $items->where('item_type', 'costume')->pluck('item_id')->toArray();

        return compact('elements', 'costumes');
    }

    public function purchaseElement(User $user, string $filename): UserPurchasedItem
    {
        $price = $this->avatarConfig->getItemPrice($filename);

        if ($price === null) {
            throw new \Exception('Item is not for sale');
        }

        if ($user->ownsAvatarItem($filename)) {
            throw new \Exception('You already own this item');
        }

        $this->creditService->spendCredits(
            $user,
            $price,
            'purchase_item',
            $filename,
            "Purchased item: {$filename}"
        );

        return UserPurchasedItem::create([
            'user_id' => $user->id,
            'item_type' => 'element',
            'item_id' => $filename,
            'layer' => $this->classifyLayer($filename),
            'price_paid' => $price,
            'purchased_at' => now(),
        ]);
    }

    public function purchaseCostume(User $user, string $costumeId): UserPurchasedItem
    {
        $costume = $this->avatarConfig->getCostumeById($costumeId);

        if (! $costume) {
            throw new \Exception('Costume not found');
        }

        if ($user->ownsCostume($costumeId)) {
            throw new \Exception('You already own this costume');
        }

        $this->creditService->spendCredits(
            $user,
            $costume['price'],
            'purchase_costume',
            $costumeId,
            "Purchased costume: {$costume['name']}"
        );

        return UserPurchasedItem::create([
            'user_id' => $user->id,
            'item_type' => 'costume',
            'item_id' => $costumeId,
            'price_paid' => $costume['price'],
            'purchased_at' => now(),
        ]);
    }

    public function getOwnedFilenames(User $user): array
    {
        return $user->purchasedItems()
            ->where('item_type', 'element')
            ->pluck('item_id')
            ->toArray();
    }

    public function classifyLayer(string $filename): ?string
    {
        if (str_starts_with($filename, 'eye')) {
            return 'eyes';
        }
        if (str_starts_with($filename, 'hair') || str_starts_with($filename, 'haur')) {
            return 'hair';
        }
        if (str_starts_with($filename, 'beard')) {
            return 'beard';
        }

        return null;
    }
}
