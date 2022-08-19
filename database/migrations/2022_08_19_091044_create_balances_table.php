<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBalancesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('paid_to')->nullable();
            $table->unsignedBigInteger('paid_by')->nullable();
            $table->decimal('amount', 8, 2)->default(0);
            $table->timestamps();

            $table->foreign('paid_to')->references('id')->on('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('paid_by')->references('id')->on('users')->cascadeOnUpdate()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('balances');
    }
}
