<?php

use App\Models\User;
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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->restrictOnDelete();
            $table->string('order_id')->unique();

            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount', 12, 2);
            $table->decimal('tax', 12, 2);
            $table->decimal('shipping_charge', 12, 2);
            $table->decimal('total', 12, 2);

            $table->string('status')->default('pending');
            $table->string('cancel_reason')->nullable();
            $table->string('payment_method')->default('cod');
            $table->string('payment_status')->default('pending');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
