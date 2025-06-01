<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RoomType extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_id', 'name', 'description', 'base_price', 'capacity', 
        'size', 'amenities', 'images', 'status'
    ];

    protected $casts = [
        'amenities' => 'array',
        'images' => 'array',
        'base_price' => 'decimal:2',
        'size' => 'decimal:2',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function rooms()
    {
        return $this->hasMany(Room::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function availableRooms($checkIn, $checkOut)
    {
        return $this->rooms()
            ->where('status', 'available')
            ->whereNotIn('id', function ($query) use ($checkIn, $checkOut) {
                $query->select('room_id')
                      ->from('reservations')
                      ->where('status', '!=', 'cancelled')
                      ->where(function ($q) use ($checkIn, $checkOut) {
                          $q->whereBetween('check_in_date', [$checkIn, $checkOut])
                            ->orWhereBetween('check_out_date', [$checkIn, $checkOut])
                            ->orWhere(function ($q2) use ($checkIn, $checkOut) {
                                $q2->where('check_in_date', '<=', $checkIn)
                                   ->where('check_out_date', '>=', $checkOut);
                            });
                      });
            });
    }
}
