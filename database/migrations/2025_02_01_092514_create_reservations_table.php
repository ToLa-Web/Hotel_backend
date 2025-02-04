<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id('reservationID'); // Primary key
            $table->string('userName');
            $table->date('startDate');
            $table->date('endDate');
            $table->integer('amountPeople');
            $table->string('imageRoom')->nullable();
            $table->integer('floor');
            $table->enum('status', ['paid', 'not paid'])->default('not paid'); // Status field with enum
            $table->string('email');
            $table->timestamps(); // Created at and updated at timestamps
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('reservations');
    }
};