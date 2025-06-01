<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Hotel;
use App\Models\RoomType;
use App\Models\Room;
use App\Models\Reservation;
use App\Models\Payment;
use App\Models\Review;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Disable foreign key checks for clean truncate
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Truncate all tables
        DB::table('reviews')->truncate();
        DB::table('payments')->truncate();
        DB::table('reservations')->truncate();
        DB::table('rooms')->truncate();
        DB::table('room_types')->truncate();
        DB::table('hotels')->truncate();
        DB::table('users')->truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Create users
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'Admin',
            'phone' => '1234567890',
            'address' => 'Admin Address',
            'date_of_birth' => '1990-01-01',
        ]);

        $owner = User::create([
            'name' => 'Hotel Owner',
            'email' => 'owner@example.com',
            'password' => Hash::make('password'),
            'role' => 'Owner',
            'phone' => '9876543210',
            'address' => 'Owner Address',
            'date_of_birth' => '1985-06-15',
        ]);

        $user = User::create([
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
            'role' => 'User',
            'phone' => '5555555555',
            'address' => 'User Address',
            'date_of_birth' => '2000-08-21',
        ]);

        // Create hotel
        $hotel = Hotel::create([
            'owner_id' => $owner->id,
            'name' => 'Sunrise Hotel',
            'slug' => 'sunrise-hotel',
            'description' => 'A beautiful beachfront hotel.',
            'address' => 'Beach Road',
            'city' => 'Miami',
            'state' => 'FL',
            'country' => 'USA',
            'postal_code' => '33101',
            'latitude' => 25.7617,
            'longitude' => -80.1918,
            'phone' => '3051234567',
            'email' => 'contact@sunrisehotel.com',
            'website' => 'https://sunrisehotel.com',
            'amenities' => json_encode(['wifi', 'pool', 'gym']),
            'images' => json_encode(['img1.jpg', 'img2.jpg']),
            'rating' => 4.5,
            'total_reviews' => 10,
            'status' => 'active',
        ]);

        // Room type
        $roomType = RoomType::create([
            'hotel_id' => $hotel->id,
            'name' => 'Deluxe Room',
            'description' => 'Spacious room with ocean view.',
            'base_price' => 150.00,
            'capacity' => 2,
            'size' => 30.5,
            'amenities' => json_encode(['tv', 'wifi', 'ac']),
            'images' => json_encode(['deluxe1.jpg']),
            'status' => 'active',
        ]);

        // Room
        $room = Room::create([
            'hotel_id' => $hotel->id,
            'room_type_id' => $roomType->id,
            'room_number' => '101',
            'floor' => '1',
            'status' => 'available',
        ]);

        // Reservation
        $reservation = Reservation::create([
            'reservation_code' => strtoupper(Str::random(8)),
            'user_id' => $user->id,
            'hotel_id' => $hotel->id,
            'room_id' => $room->id,
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->addDays(3)->toDateString(),
            'nights' => 3,
            'adults' => 2,
            'children' => 1,
            'room_rate' => 150,
            'total_amount' => 450,
            'paid_amount' => 200,
            'pending_amount' => 250,
            'status' => 'confirmed',
            'payment_status' => 'partial',
            'special_requests' => 'High floor room',
            'confirmed_at' => now(),
        ]);

        // Payment
        Payment::create([
            'reservation_id' => $reservation->id,
            'payment_id' => strtoupper(Str::random(10)),
            'amount' => 200.00,
            'currency' => 'USD',
            'payment_method' => 'credit_card',
            'status' => 'completed',
            'gateway' => 'stripe',
            'gateway_response' => json_encode(['transaction' => 'success']),
            'processed_at' => now(),
        ]);

        // Review
        Review::create([
            'user_id' => $user->id,
            'hotel_id' => $hotel->id,
            'reservation_id' => $reservation->id,
            'rating' => 5,
            'comment' => 'Amazing stay!',
            'ratings' => json_encode(['cleanliness' => 5, 'service' => 5]),
            'status' => 'approved',
        ]);
    }
}
