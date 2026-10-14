<?php

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
        Schema::table('payment_refunds', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable();
            $table->string('submission_status')->default('not_submitted');
        });
    }

    public function down(): void
    {
        Schema::table('payment_refunds', function (Blueprint $table) {
            $table->dropColumn(['submitted_at', 'submission_status']);
        });
    }
};
