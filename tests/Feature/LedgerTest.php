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

    public function test_spending_exactly_all_cash_succeeds(): void
    {
        $client = Client::create(['name' => 'Ana']);
        $this->postJson('/api/transactions', ['client_id' => $client->id, 'type' => 'deposit', 'amount' => 500]);

        $response = $this->postJson('/api/transactions', [
            'client_id' => $client->id,
            'type' => 'buy',
            'ticker' => 'AAPL',
            'quantity' => 5,
            'price' => 100,
        ]);

        $response->assertCreated();
        $response->assertJson(['balance' => 0, 'holdings' => ['AAPL' => 5]]);
    }

    public function test_selling_exactly_all_shares_succeeds(): void
    {
        $client = Client::create(['name' => 'Ana']);
        $this->postJson('/api/transactions', ['client_id' => $client->id, 'type' => 'deposit', 'amount' => 1000]);
        $this->postJson('/api/transactions', ['client_id' => $client->id, 'type' => 'buy', 'ticker' => 'AAPL', 'quantity' => 5, 'price' => 100]);

        $response = $this->postJson('/api/transactions', [
            'client_id' => $client->id,
            'type' => 'sell',
            'ticker' => 'AAPL',
            'quantity' => 5,
            'price' => 100,
        ]);

        $response->assertCreated();
        $response->assertJson(['balance' => 1000, 'holdings' => []]);
    }

    public function test_zero_amount_is_rejected(): void
    {
        $client = Client::create(['name' => 'Ana']);

        $response = $this->postJson('/api/transactions', [
            'client_id' => $client->id,
            'type' => 'deposit',
            'amount' => 0,
        ]);

        $response->assertStatus(422);
    }

    public function test_negative_amount_is_rejected(): void
    {
        $client = Client::create(['name' => 'Ana']);

        $response = $this->postJson('/api/transactions', [
            'client_id' => $client->id,
            'type' => 'deposit',
            'amount' => -100,
        ]);

        $response->assertStatus(422);
    }

    public function test_non_integer_quantity_is_rejected(): void
    {
        $client = Client::create(['name' => 'Ana']);
        $this->postJson('/api/transactions', ['client_id' => $client->id, 'type' => 'deposit', 'amount' => 1000]);

        $response = $this->postJson('/api/transactions', [
            'client_id' => $client->id,
            'type' => 'buy',
            'ticker' => 'AAPL',
            'quantity' => 2.5,
            'price' => 100,
        ]);

        $response->assertStatus(422);
    }

    public function test_unknown_transaction_type_is_rejected(): void
    {
        $client = Client::create(['name' => 'Ana']);

        $response = $this->postJson('/api/transactions', [
            'client_id' => $client->id,
            'type' => 'yolo',
            'amount' => 100,
        ]);

        $response->assertStatus(422);
    }

    public function test_nonexistent_client_is_rejected(): void
    {
        $response = $this->postJson('/api/transactions', [
            'client_id' => 999999,
            'type' => 'deposit',
            'amount' => 100,
        ]);

        $response->assertStatus(422);
    }

    public function test_duplicate_client_name_is_rejected(): void
    {
        Client::create(['name' => 'Ana']);

        $response = $this->postJson('/api/clients', ['name' => 'Ana']);

        $response->assertStatus(422);
    }

    public function test_selling_a_ticker_never_bought_is_rejected(): void
    {
        $client = Client::create(['name' => 'Ana']);
        $this->postJson('/api/transactions', ['client_id' => $client->id, 'type' => 'deposit', 'amount' => 1000]);

        $response = $this->postJson('/api/transactions', [
            'client_id' => $client->id,
            'type' => 'sell',
            'ticker' => 'TSLA',
            'quantity' => 1,
            'price' => 100,
        ]);

        $response->assertStatus(422);
    }

    public function test_clients_are_independent(): void
    {
        $ana = Client::create(['name' => 'Ana']);
        $marko = Client::create(['name' => 'Marko']);

        $this->postJson('/api/transactions', ['client_id' => $ana->id, 'type' => 'deposit', 'amount' => 1000]);
        $this->postJson('/api/transactions', ['client_id' => $ana->id, 'type' => 'buy', 'ticker' => 'AAPL', 'quantity' => 5, 'price' => 100]);

        $response = $this->getJson("/api/clients/{$marko->id}");

        $response->assertJson(['balance' => 0, 'holdings' => []]);
    }
}
