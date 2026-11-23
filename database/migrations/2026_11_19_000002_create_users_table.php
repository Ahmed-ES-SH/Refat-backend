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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email', 190)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password', 255)->nullable();
            $table->string('google_id', 64)->nullable()->unique();
            $table->string('role', 20)->default('patient');
            $table->string('status', 20)->default('active');
            $table->string('phone', 30)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 10)->nullable();
            $table->string('locale', 2)->default('ar');
            $table->foreignId('center_id')
                ->nullable()
                ->constrained('centers')
                ->restrictOnDelete();
            $table->string('stripe_customer_id', 64)->nullable()->unique();
            $table->timestamps();

            $table->index(['role', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
