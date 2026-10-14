<?php

use App\Models\Order;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignIdFor(Order::class)->constrained()->restrictOnDelete();

            $table->string('gateway');

            $table->string('gateway_payment_id')->nullable();
            $table->string('gateway_order_id')->nullable();

            $table->decimal('amount' ,12, 2);

            $table->string('currency')->default('INR');
            $table->string('status')->default('pending');
            $table->string('payment_method')->nullable();
            $table->string('failure_reason')->nullable();
            $table->json('gateway_response')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
