<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Exceptions\InsufficientFundsException;
use App\Exceptions\InsufficientSharesException;
use App\Models\Client;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class LedgerService
{
    /**
     * Current cash balance: sum of everything in, minus everything out.
     * Deposits and sell proceeds increase cash; withdrawals and buys decrease it.
     */
    public function balance(Client $client): float
    {
        $in = $client->transactions()
            ->whereIn('type', [TransactionType::Deposit->value, TransactionType::Sell->value])
            ->sum('amount');

        $out = $client->transactions()
            ->whereIn('type', [TransactionType::Withdraw->value, TransactionType::Buy->value])
            ->sum('amount');

        return (float) $in - (float) $out;
    }

    /**
     * Current holdings: quantity bought minus quantity sold, per ticker.
     * Tickers netted down to zero are dropped from the result.
     */

    public function holdings(Client $client): array
    {
        $bought = $client->transactions()
            ->where('type', TransactionType::Buy->value)
            ->selectRaw('ticker, SUM(quantity) as qty')
            ->groupBy('ticker')
            ->pluck('qty', 'ticker');

        $sold = $client->transactions()
            ->where('type', TransactionType::Sell->value)
            ->selectRaw('ticker, SUM(quantity) as qty')
            ->groupBy('ticker')
            ->pluck('qty', 'ticker');

        $tickers = $bought->keys()->merge($sold->keys())->unique();

        $holdings = [];
        foreach ($tickers as $ticker) {
            $net = (int) ($bought[$ticker] ?? 0) - (int) ($sold[$ticker] ?? 0);

            if ($net > 0) {
                $holdings[$ticker] = $net;
            }
        }

        return $holdings;
    }

    /** Records a cash deposit. 
     * No business rule validation required – amount is always added to the balance. 
     **/
    public function deposit(Client $client, float $amount): Transaction
    {
        return DB::transaction(function () use ($client, $amount) {
            $this->lock($client);

            return Transaction::create([
                'client_id' => $client->id,
                'type' => TransactionType::Deposit,
                'amount' => $amount,
            ]);
        });
    }

    /** Records a cash withdrawal. 
     * Ensures cash is sufficient before creating the transaction; 
     * otherwise throws InsufficientFundsException. */
    public function withdraw(Client $client, float $amount): Transaction
    {
        return DB::transaction(function () use ($client, $amount) {
            $this->lock($client);

            $balance = $this->balance($client);
            if ($amount > $balance) {
                throw new InsufficientFundsException(
                    "Cannot withdraw {$amount}: available balance is {$balance}."
                );
            }

            return Transaction::create([
                'client_id' => $client->id,
                'type' => TransactionType::Withdraw,
                'amount' => $amount,
            ]);
        });
    }

    /**
     * Records a purchase of a transactionable asset. 
     * Ensures the client has enough cash to cover quantity * price; 
     * Throws InsufficientFundsException on failure. 
     */
    public function buy(Client $client, string $ticker, int $quantity, float $price): Transaction
    {
        return DB::transaction(function () use ($client, $ticker, $quantity, $price) {
            $this->lock($client);

            $cost = $quantity * $price;
            $balance = $this->balance($client);
            if ($cost > $balance) {
                throw new InsufficientFundsException(
                    "Cannot buy {$quantity} {$ticker} at {$price}: cost {$cost} exceeds available balance {$balance}."
                );
            }
            return Transaction::create([
                'client_id' => $client->id,
                'type' => TransactionType::Buy,
                'amount' => $cost,
                'ticker' => $ticker,
                'quantity' => $quantity,
                'price' => $price,
            ]);
        });
    }

    /** Records a sale of an instrument. 
     *  Ensures the client owns enough shares of the given ticker;
     *  throws InsufficientSharesException on failure. 
     * **/

    public function sell(Client $client, string $ticker, int $quantity, float $price): Transaction
    {
        return DB::transaction(function () use ($client, $ticker, $quantity, $price) {
            $this->lock($client);

            $owned = $this->holdings($client)[$ticker] ?? 0;

            if ($quantity > $owned) {
                throw new InsufficientSharesException(
                    "Cannot sell {$quantity} {$ticker}: only {$owned} owned."
                );
            }

            return Transaction::create([
                'client_id' => $client->id,
                'type' => TransactionType::Sell,
                'amount' => $quantity * $price,
                'ticker' => $ticker,
                'quantity' => $quantity,
                'price' => $price,
            ]);
        });
    }

    /**
     * Row-locks the client for the duration of the enclosing DB transaction,
     * so two simultaneous requests for the same client can't both read the
     * same balance/holdings and both pass a check that only one of them should.
     */
    private function lock(Client $client): void
    {
        Client::where('id', $client->id)->lockForUpdate()->first();
    }

}