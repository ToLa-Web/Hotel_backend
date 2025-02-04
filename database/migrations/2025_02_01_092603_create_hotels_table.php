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
        Schema::create('hotels', function (Blueprint $table) {
            $table->id('hotelId'); // Primary key
            $table->unsignedBigInteger('reservationID'); // Foreign key for reservations
            $table->unsignedBigInteger('roomID'); // Foreign key for rooms
            $table->string('hotelName');
            $table->integer('amountRoom');
            $table->string('location');
            $table->string('image')->nullable(); // Image field (nullable)
            $table->timestamps(); // Created at and updated at timestamps

            // Foreign key constraints
            $table->foreign('reservationID')->references('reservationID')->on('reservations')->onDelete('cascade');
            $table->foreign('roomID')->references('roomId')->on('rooms')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('hotels');
    }
};