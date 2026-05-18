<?php

namespace App\Services;

class AvatarConfigService
{
    private ?array $shopConfig = null;

    private function loadConfig(): array
    {
        if ($this->shopConfig !== null) {
            return $this->shopConfig;
        }

        $path = storage_path('app/avatar-shop.json');

        if (! file_exists($path)) {
            return $this->shopConfig = ['paid' => [], 'costumes' => []];
        }

        $content = file_get_contents($path);
        $this->shopConfig = json_decode($content, true) ?: ['paid' => [], 'costumes' => []];

        return $this->shopConfig;
    }

    public function getPaidItems(): array
    {
        return $this->loadConfig()['paid'] ?? [];
    }

    public function getCostumes(): array
    {
        return $this->loadConfig()['costumes'] ?? [];
    }

    public function getItemPrice(string $filename): ?int
    {
        $paid = $this->getPaidItems();

        return $paid[$filename] ?? null;
    }

    public function isPaidItem(string $filename): bool
    {
        return isset($this->getPaidItems()[$filename]);
    }

    public function getCostumeById(string $id): ?array
    {
        foreach ($this->getCostumes() as $costume) {
            if (($costume['id'] ?? null) === $id) {
                return $costume;
            }
        }

        return null;
    }

    public function getAllShopItems(): array
    {
        return $this->loadConfig();
    }
}
