<?php

namespace Tests\Feature;

use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_deposit_increases_balance(): void
    {
        $client = Client::create(['name' => 'Ana']);

        $response = $this->postJson('/api/transactions', [
            'client_id' => $client->id,
            'type' => 'deposit',
            'amount' => 1000,
        ]);

        $response->assertCreated();
        $response->assertJson(['balance' => 1000]);
    }

    public function test_buy_decreases_balance_and_adds_holding(): void
    {
        $client = Client::create(['name' => 'Ana']);
        $this->postJson('/api/transactions', ['client_id' => $client->id, 'type' => 'deposit', 'amount' => 1000]);

        $response = $this->postJson('/api/transactions', [
            'client_id' => $client->id,
            'type' => 'buy',
            'ticker' => 'AAPL',
            'quantity' => 5,
            'price' => 100,
        ]);

        $response->assertCreated();
        $response->assertJson(['balance' => 500, 'holdings' => ['AAPL' => 5]]);
    }

    public function test_sell_increases_balance_and_reduces_holding(): void
    {
        $client = Client::create(['name' => 'Ana']);
        $this->postJson('/api/transactions', ['client_id' => $client->id, 'type' => 'deposit', 'amount' => 1000]);
        $this->postJson('/api/transactions', ['client_id' => $client->id, 'type' => 'buy', 'ticker' => 'AAPL', 'quantity' => 5, 'price' => 100]);

        $response = $this->postJson('/api/transactions', [
            'client_id' => $client->id,
            'type' => 'sell',
            'ticker' => 'AAPL',
            'quantity' => 3,
            'price' => 120,
        ]);

        $response->assertCreated();
        $response->assertJson(['balance' => 860, 'holdings' => ['AAPL' => 2]]);
    }

    public function test_withdrawal_over_balance_is_rejected(): void
    {
        $client = Client::create(['name' => 'Ana']);
        $this->postJson('/api/transactions', ['client_id' => $client->id, 'type' => 'deposit', 'amount' => 500]);

        $response = $this->postJson('/api/transactions', [
            'client_id' => $client->id,
            'type' => 'withdraw',
            'amount' => 600,
        ]);

        $response->assertStatus(422);

        $check = $this->getJson("/api/clients/{$client->id}");
        $check->assertJson(['balance' => 500]);
    }

    public function test_sell_over_holdings_is_rejected(): void
    {
        $client = Client::create(['name' => 'Ana']);
        $this->postJson('/api/transactions', ['client_id' => $client->id, 'type' => 'deposit', 'amount' => 1000]);
        $this->postJson('/api/transactions', ['client_id' => $client->id, 'type' => 'buy', 'ticker' => 'AAPL', 'quantity' => 5, 'price' => 100]);

        $response = $this->postJson('/api/transactions', [
            'client_id' => $client->id,
            'type' => 'sell',
            'ticker' => 'AAPL',
            'quantity' => 8,
            'price' => 100,
        ]);

        $response->assertStatus(422);

        $check = $this->getJson("/api/clients/{$client->id}");
        $check->assertJson(['balance' => 500, 'holdings' => ['AAPL' => 5]]);
    }

    public function test_buy_over_balance_is_rejected(): void
    {
        $client = Client::create(['name' => 'Ana']);
        $this->postJson('/api/transactions', ['client_id' => $client->id, 'type' => 'deposit', 'amount' => 100]);

        $response = $this->postJson('/api/transactions', [
            'client_id' => $client->id,
            'type' => 'buy',
            'ticker' => 'AAPL',
            'quantity' => 5,
            'price' => 100,
        ]);

        $response->assertStatus(422);

        $check = $this->getJson("/api/clients/{$client->id}");
        $check->assertJson(['balance' => 100, 'holdings' => []]);
    }
}
