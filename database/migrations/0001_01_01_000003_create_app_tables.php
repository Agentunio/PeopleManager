<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->decimal('price', 10, 2);
        });

        Schema::create('workers', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->boolean('is_student')->default(false);
            $table->boolean('is_employed')->default(true);
            $table->date('contract_from')->nullable();
            $table->date('contract_to')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workers');
        Schema::dropIfExists('settings');
    }
};
