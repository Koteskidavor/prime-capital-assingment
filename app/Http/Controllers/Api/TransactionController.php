<?php

namespace App\Http\Controllers\Api;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransactionRequest;
use App\Models\Client;
use App\Services\LedgerService;
use Illuminate\Http\JsonResponse;

class TransactionController extends Controller
{
    public function __construct(private LedgerService $ledger)
    {
    }

    public function store(StoreTransactionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $client = Client::findOrFail($data['client_id']);
        $type = TransactionType::from($data['type']);

        $transaction = match ($type) {
            TransactionType::Deposit => $this->ledger->deposit($client, $data['amount']),
            TransactionType::Withdraw => $this->ledger->withdraw($client, $data['amount']),
            TransactionType::Buy => $this->ledger->buy($client, $data['ticker'], $data['quantity'], $data['price']),
            TransactionType::Sell => $this->ledger->sell($client, $data['ticker'], $data['quantity'], $data['price']),
        };

        return response()->json([
            'id' => $transaction->id,
            'client_id' => $transaction->client_id,
            'type' => $transaction->type->value,
            'amount' => $transaction->amount,
            'ticker' => $transaction->ticker,
            'quantity' => $transaction->quantity,
            'price' => $transaction->price,
            'balance' => $this->ledger->balance($client),
            'holdings' => $this->ledger->holdings($client),
        ], 201);
    }
}