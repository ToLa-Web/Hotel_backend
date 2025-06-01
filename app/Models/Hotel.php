<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Hotel extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id', 'name', 'slug', 'description', 'address', 'city', 'state', 
        'country', 'postal_code', 'latitude', 'longitude', 'phone', 'email', 
        'website', 'amenities', 'images', 'status'
    ];

    protected $casts = [
        'amenities' => 'array',
        'images' => 'array',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function roomTypes()
    {
        return $this->hasMany(RoomType::class);
    }

    public function rooms()
    {
        return $this->hasMany(Room::class);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function availableRooms($checkIn, $checkOut, $roomTypeId = null)
    {
        $query = $this->rooms()->where('status', 'available');
        
        if ($roomTypeId) {
            $query->where('room_type_id', $roomTypeId);
        }

        // Exclude rooms that are booked during the requested period
        $query->whereNotIn('id', function($subQuery) use ($checkIn, $checkOut) {
            $subQuery->select('room_id')
                ->from('reservations')
                ->where('status', '!=', 'cancelled')
                ->where(function($q) use ($checkIn, $checkOut) {
                    $q->whereBetween('check_in_date', [$checkIn, $checkOut])
                      ->orWhereBetween('check_out_date', [$checkIn, $checkOut])
                      ->orWhere(function($q2) use ($checkIn, $checkOut) {
                          $q2->where('check_in_date', '<=', $checkIn)
                             ->where('check_out_date', '>=', $checkOut);
                      });
                });
        });

        return $query;
    }
}