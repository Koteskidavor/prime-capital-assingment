<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Services\LedgerService;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $ledger = app(LedgerService::class);

        // Ana: Deposit 1000, buy 5 AAPL at 100, sell 3 AAPL at 120.
        // Final state: 1000 - 500 + 360 = 860 cash, 2 AAPL held.
        $ana = Client::create(['name' => 'Ana']);
        $ledger->deposit($ana, 1000);
        $ledger->buy($ana, 'AAPL', 5, 100);
        $ledger->sell($ana, 'AAPL', 3, 120);

        // Marko: Deposit 5000, buy 10 TSLA at 200, buy 8 MSFT at 150, withdraw 500.
        // Final state: 5000 - 2000 - 1200 - 500 = 1300 cash, 10 TSLA and 8 MSFT held.
        $marko = Client::create(['name' => 'Marko']);
        $ledger->deposit($marko, 5000);
        $ledger->buy($marko, 'TSLA', 10, 200);
        $ledger->buy($marko, 'MSFT', 8, 150);
        $ledger->withdraw($marko, 500);

        // Elena: Deposit 2000, buy 4 GOOG at 300, sell 4 GOOG at 320.
        // Final state: 2000 - 1200 + 1280 = 2080 cash, no holdings.
        $elena = Client::create(['name' => 'Elena']);
        $ledger->deposit($elena, 2000);
        $ledger->buy($elena, 'GOOG', 4, 300);
        $ledger->sell($elena, 'GOOG', 4, 320);
    }
}
