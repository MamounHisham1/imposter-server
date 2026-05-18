<?php

namespace App\Http\Controllers;

use App\Services\ShopService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ShopController extends Controller
{
    public function __construct(
        private ShopService $shopService,
    ) {}

    public function index()
    {
        $user = Auth::user();
        $shopItems = $this->shopService->getShopItems();
        $inventory = $this->shopService->getUserInventory($user);

        return Inertia::render('Shop', [
            'shopItems' => $shopItems,
            'inventory' => $inventory,
            'credits' => $user->credits,
        ]);
    }

    public function buyElement(Request $request)
    {
        $validated = $request->validate([
            'filename' => 'required|string',
        ]);

        try {
            $this->shopService->purchaseElement(Auth::user(), $validated['filename']);
        } catch (\Exception $e) {
            throw ValidationException::withMessages(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Item purchased!');
    }

    public function buyCostume(Request $request)
    {
        $validated = $request->validate([
            'costume_id' => 'required|string',
        ]);

        try {
            $this->shopService->purchaseCostume(Auth::user(), $validated['costume_id']);
        } catch (\Exception $e) {
            throw ValidationException::withMessages(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Costume purchased!');
    }

    public function inventory()
    {
        $user = Auth::user();

        return response()->json($this->shopService->getUserInventory($user));
    }
}
