<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PurchasePaymentController extends Controller
{
    public function store(Request $request, Purchase $purchase)
    {
        $paid = (float) $purchase->payments()->sum('amount');
        $returned = (float) $purchase->returns()->sum('total_amount');
        $netTotal = max((float) $purchase->grand_total - $returned, 0);
        $due = max($netTotal - $paid, 0);

        $data = $request->validate([
            'paid_on' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.$due],
            'method' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $purchase->payments()->create([
            ...$data,
            'created_by' => Auth::id(),
        ]);

        return back()->with('success', 'Purchase payment recorded.');
    }
}
