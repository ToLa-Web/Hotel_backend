`<?php

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
        // Schema::create('users', function (Blueprint $table) {
        //     $table->id();
        //     $table->string('name');
        //     $table->string('email')->unique();
        //     $table->timestamp('email_verified_at')->nullable();
        //     $table->string('password');
        //     $table->rememberToken();
        //     $table->timestamps();
        // });
        // database/migrations/create_users_table.php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->timestamp('email_verified_at')->nullable();
    $table->string('password');
    $table->enum('role', ['Admin', 'Owner', 'User'])->default('User');
    $table->string('phone')->nullable();
    $table->text('address')->nullable();
    $table->date('date_of_birth')->nullable();
    $table->enum('status', ['active', 'inactive'])->default('active');
    $table->rememberToken();
    $table->timestamps();
});

// database/migrations/create_hotels_table.php
Schema::create('hotels', function (Blueprint $table) {
    $table->id();
    $table->foreignId('owner_id')->constrained('users')->onDelete('cascade');
    $table->string('name');
    $table->string('slug')->unique();
    $table->text('description');
    $table->string('address');
    $table->string('city');
    $table->string('state');
    $table->string('country');
    $table->string('postal_code');
    $table->decimal('latitude', 10, 8)->nullable();
    $table->decimal('longitude', 11, 8)->nullable();
    $table->string('phone');
    $table->string('email');
    $table->string('website')->nullable();
    $table->json('amenities')->nullable(); // ["wifi", "pool", "gym", "spa"]
    $table->longText('images')->nullable();
    $table->decimal('rating', 2, 1)->default(0);
    $table->integer('total_reviews')->default(0);
    $table->enum('status', ['active', 'inactive', 'pending'])->default('pending');
    $table->timestamps();
});

// database/migrations/create_room_types_table.php
Schema::create('room_types', function (Blueprint $table) {
    $table->id();
    $table->foreignId('hotel_id')->constrained()->onDelete('cascade');
    $table->string('name'); // Standard, Deluxe, Suite
    $table->text('description');
    $table->decimal('base_price', 8, 2);
    $table->integer('capacity'); // max occupancy
    $table->decimal('size', 6, 2)->nullable(); // in sq meters
    $table->json('amenities')->nullable();
    $table->json('images')->nullable();
    $table->enum('status', ['active', 'inactive'])->default('active');
    $table->timestamps();
});

// database/migrations/create_rooms_table.php
Schema::create('rooms', function (Blueprint $table) {
    $table->id();
    $table->foreignId('hotel_id')->constrained()->onDelete('cascade');
    $table->foreignId('room_type_id')->constrained()->onDelete('cascade');
    $table->string('room_number');
    $table->string('floor');
    $table->enum('status', ['available', 'occupied', 'maintenance', 'out_of_order'])->default('available');
    $table->text('notes')->nullable();
    $table->timestamps();
    
    $table->unique(['hotel_id', 'room_number']);
});

// database/migrations/create_reservations_table.php
Schema::create('reservations', function (Blueprint $table) {
    $table->id();
    $table->string('reservation_code')->unique();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->foreignId('hotel_id')->constrained()->onDelete('cascade');
    $table->foreignId('room_id')->constrained()->onDelete('cascade');
    $table->date('check_in_date');
    $table->date('check_out_date');
    $table->integer('nights');
    $table->integer('adults');
    $table->integer('children')->default(0);
    $table->decimal('room_rate', 8, 2);
    $table->decimal('total_amount', 10, 2);
    $table->decimal('paid_amount', 10, 2)->default(0);
    $table->decimal('pending_amount', 10, 2)->default(0);
    $table->enum('status', ['pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled', 'no_show'])->default('pending');
    $table->enum('payment_status', ['pending', 'partial', 'paid', 'refunded'])->default('pending');
    $table->text('special_requests')->nullable();
    $table->timestamp('confirmed_at')->nullable();
    $table->timestamp('checked_in_at')->nullable();
    $table->timestamp('checked_out_at')->nullable();
    $table->timestamps();
});

// database/migrations/create_payments_table.php
Schema::create('payments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('reservation_id')->constrained()->onDelete('cascade');
    $table->string('payment_id')->unique(); // Stripe/PayPal ID
    $table->decimal('amount', 10, 2);
    $table->string('currency', 3)->default('USD');
    $table->enum('payment_method', ['credit_card', 'debit_card', 'paypal', 'bank_transfer', 'cash']);
    $table->enum('status', ['pending', 'completed', 'failed', 'refunded', 'cancelled']);
    $table->string('gateway'); // stripe, paypal, etc.
    $table->json('gateway_response')->nullable();
    $table->timestamp('processed_at')->nullable();
    $table->timestamps();
});

// database/migrations/create_reviews_table.php
Schema::create('reviews', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->foreignId('hotel_id')->constrained()->onDelete('cascade');
    $table->foreignId('reservation_id')->constrained()->onDelete('cascade');
    $table->integer('rating'); // 1-5
    $table->text('comment')->nullable();
    $table->json('ratings')->nullable(); // {"cleanliness": 5, "service": 4, "location": 5}
    $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
};
