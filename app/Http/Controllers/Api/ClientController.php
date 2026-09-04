<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClientRequest;
use App\Models\Client;
use App\Services\LedgerService;
use Illuminate\Http\JsonResponse;

class ClientController extends Controller
{
    public function __construct(private readonly LedgerService $ledger)
    {

    }

    public function index(): JsonResponse
    {
        $clients = Client::all()->map(fn(Client $client) => [
            'id' => $client->id,
            'name' => $client->name,
            'balance' => $this->ledger->balance($client),
            'holdings' => $this->ledger->holdings($client),
        ]);

        return response()->json($clients);
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        $client = Client::create($request->validated());

        return response()->json([
            'id' => $client->id,
            'name' => $client->name,
            'balance' => 0,
            'holdings' => [],
        ], 201);
    }

    public function show(Client $client): JsonResponse
    {
        return response()->json([
            'id' => $client->id,
            'name' => $client->name,
            'balance' => $this->ledger->balance($client),
            'holdings' => $this->ledger->holdings($client),
        ]);
    }
}