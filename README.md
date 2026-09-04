# Prime Capital Assingment

### Prerequisites
- PHP ≥ 8.3
- Composer
- SQLite (bundled with PHP — nothing extra to install)

## Local Setup

```bash
# 1. Clone the repository
git clone https://github.com/Koteskidavor/prime-capital-assingment.git
cd prime-capital-assingment

# 2. Install PHP dependencies
composer install

# 3. Copy the environment file and generate an app key
cp .env.example .env
php artisan key:generate

# 4. Run migrations and seed sample data
php artisan migrate --seed

# 5. Start the development server
php artisan serve
```

The `--seed` flag creates three sample clients (Ana, Marko, Elena) with pre-existing transactions so you can test the API immediately without entering data manually.

The server will be available at `http://localhost:8000`.

---

## API Reference

All endpoints are under `/api` and return JSON.

### Create a client

```
POST /api/clients
```

**Request body:**
```json
{
    "name": "Ana"
}
```

**Response `201`:**
```json
{
    "id": 1,
    "name": "Ana",
    "balance": "0.00",
    "holdings": []
}
```

---

### List all clients

```
GET /api/clients
```

**Response `200`:**
```json
[
    {
        "id": 1,
        "name": "Ana",
        "balance": "860.00",
        "holdings": { "AAPL": 2 }
    },
    {
        "id": 2,
        "name": "Marko",
        "balance": "1300.00",
        "holdings": { "TSLA": 10, "MSFT": 8 }
    }
]
```

---

### Get a single client

```
GET /api/clients/{id}
```

**Response `200`:**
```json
{
    "id": 1,
    "name": "Ana",
    "balance": "860.00",
    "holdings": { "AAPL": 2 }
}
```

---

### Record a transaction

```
POST /api/transactions
```

#### Deposit

```json
{
    "client_id": 1,
    "type": "deposit",
    "amount": 1000
}
```

#### Withdrawal

```json
{
    "client_id": 1,
    "type": "withdraw",
    "amount": 200
}
```

#### Buy

```json
{
    "client_id": 1,
    "type": "buy",
    "ticker": "AAPL",
    "quantity": 5,
    "price": 100
}
```

#### Sell

```json
{
    "client_id": 1,
    "type": "sell",
    "ticker": "AAPL",
    "quantity": 3,
    "price": 120
}
```

**Successful response `201`:**
```json
{
    "id": 4,
    "client_id": 1,
    "type": "sell",
    "amount": "360.00",
    "ticker": "AAPL",
    "quantity": 3,
    "price": "120.00",
    "balance": "860.00",
    "holdings": { "AAPL": 2 }
}
```

**Failed response `422` (rule violation):**
```json
{
    "message": "Cannot withdraw 600: available balance is 500."
}
```

---

## Business Rules

- A client's cash balance can **never go negative**. Attempting a withdrawal or buy that exceeds the available balance returns a `422` error and leaves the account unchanged.
- A client **cannot sell more shares** of an instrument than they currently hold. Attempting to do so returns a `422` error and leaves the account unchanged.

---

## Running Tests

```bash
php artisan test
```

The test suite covers:

**Successful transactions paths**
- Deposit increases balance
- Buy decreases balance and adds a holding
- Sell increases balance and reduces a holding
- Spending exactly all available cash succeeds (zero balance after buy)
- Selling exactly all held shares succeeds (empty holdings after sell)

**Business rule rejections (each also verifies account state is unchanged)**
- Withdrawal above balance is rejected
- Buy above balance is rejected
- Sell above held quantity is rejected
- Selling a ticker that was never bought is rejected

**Input validation rejections**
- Zero amount is rejected
- Negative amount is rejected
- Non-integer quantity (fractional shares) is rejected
- Unknown transaction type is rejected
- Non-existent client ID is rejected
- Duplicate client name is rejected

**Isolation**
- Transactions on one client have no effect on another client's balance or holdings

## Why this way

- **Cash balance and holdings are calculated from the transaction history every time they are requested.** 
Cash and holdings are calculated when needed because we sacrifice a bit of performance at scale to eliminate bugs related to inconcistent data and accuracy.

- **Every write goes through one `LedgerService`, wrapped in a database transaction with row lock on the client.**
Without the lock, two requests for the same client could both do the same action twice even before one is finished. The row lock forces them to wait until the first request is finished. 

- **SQLite and PHP Enum**
I chose SQLite because it's simple and easy to setup and works on any computer with simple configuration. SQLite doesnt have real native enum type so if I added one the validation would be duplicated in SQL and PHP risking one being updated and the other isn't, so I decided to use single PHP enum `TransactionType` ensures there is one place defining a type, and SQLite stores the string that the enum hands it.

- **Validation happens in two separate layers.**
Validation happens in two layers. the first is Form Requests which checks if the input is the right type, positive number, required field before the business logic and the second is LedgerService checks the business logic of whether the  client can afford the request. Separating these layers makes each layer focus on one problem making it easier to debug and reason about what could go wrong.

- **Custom exceptions instead of if checks in the controller.**
Instead of controller checking things like "does the user have enough cash?" or "do they own enough shares?" directly, that logic lives in `LedgerService` and when a rule is broken we get `InsufficientFundsException` or `InsufficientSharesException`. `bootstrap/app.php` catches exceptions in one place and turns them into 422 response with a clear message. I did this so the controller has one responsibility call the service and return what comes back.

