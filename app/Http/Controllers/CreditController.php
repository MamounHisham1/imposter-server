<?php

namespace App\Http\Controllers;

use App\Services\CreditService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class CreditController extends Controller
{
    public function __construct(
        private CreditService $creditService,
    ) {}

    public function index()
    {
        $user = Auth::user();
        $transactions = $this->creditService->getTransactions($user);

        return Inertia::render('Credits', [
            'credits' => $user->credits,
            'transactions' => $transactions,
        ]);
    }
}
