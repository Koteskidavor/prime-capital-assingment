<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('client_id')->constrained()->cascadeOnDelete();

            $table->string('type');
            $table->decimal('amount', 12, 2);

            $table->string('ticker')->nullable();
            $table->unsignedInteger('quantity')->nullable();
            $table->decimal('price', 12, 2)->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['client_id', 'type']);
            $table->index(['client_id', 'ticker']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
