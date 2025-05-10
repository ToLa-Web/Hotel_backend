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
        Schema::create('rooms', function (Blueprint $table) {
            $table->id('roomId'); // Primary key
            $table->unsignedBigInteger('hotelId'); // Foreign key for hotels
            $table->integer('maxOccupancy');
            $table->boolean('available')->default(true); // Boolean field for availability
            $table->decimal('pricePerNight', 10, 2); // Decimal field for price
            $table->string('roomType');
            $table->string('bedType');
            $table->string('image')->nullable(); // Image field (nullable)
            $table->text('amenities')->nullable(); // Text field for amenities (nullable)
            $table->timestamps(); // Created at and updated at timestamps

            // Foreign key constraint
            $table->foreign('hotelId')->references('hotelId')->on('hotels')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('rooms');
    }
};