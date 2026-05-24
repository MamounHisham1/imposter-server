<?php

namespace App\Http\Controllers;

use App\Services\PromoCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class PromoCodeController extends Controller
{
    public function __construct(
        private PromoCodeService $promoCodeService,
    ) {}

    public function redeem(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string',
        ]);

        $result = $this->promoCodeService->redeem(Auth::user(), $validated['code']);

        if (! $result['success']) {
            throw ValidationException::withMessages(['code' => $result['message']]);
        }

        return back()->with('success', $result['message']);
    }

    public function adminList()
    {
        $codes = $this->promoCodeService->listCodes();

        return response()->json($codes->map(fn ($code) => [
            'id' => $code->id,
            'code' => $code->code,
            'reward_type' => $code->reward_type,
            'reward_id' => $code->reward_id,
            'max_uses' => $code->max_uses,
            'uses_count' => $code->uses_count,
            'expires_at' => $code->expires_at?->toISOString(),
            'is_expired' => $code->isExpired(),
            'is_fully_used' => $code->isFullyUsed(),
            'created_at' => $code->created_at->toISOString(),
        ]));
    }

    public function adminCreate(Request $request)
    {
        $validated = $request->validate([
            'reward_type' => 'required|in:element,costume,credits',
            'reward_id' => 'required|string',
            'max_uses' => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date',
        ]);

        try {
            $code = $this->promoCodeService->generateCode(
                $validated['reward_type'],
                $validated['reward_id'],
                $validated['max_uses'] ?? null,
                $validated['expires_at'] ?? null,
            );
        } catch (\Exception $e) {
            throw ValidationException::withMessages(['error' => $e->getMessage()]);
        }

        return response()->json([
            'id' => $code->id,
            'code' => $code->code,
            'reward_type' => $code->reward_type,
            'reward_id' => $code->reward_id,
            'max_uses' => $code->max_uses,
            'uses_count' => $code->uses_count,
            'expires_at' => $code->expires_at?->toISOString(),
            'created_at' => $code->created_at->toISOString(),
        ], 201);
    }

    public function adminDelete(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|integer|exists:promo_codes,id',
        ]);

        $deleted = $this->promoCodeService->deleteCode($validated['id']);

        return response()->json(['deleted' => $deleted]);
    }
}
