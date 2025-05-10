<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;

    protected $primaryKey = 'roomId'; // Set the primary key
    public $incrementing = true; // Ensure the primary key is auto-incrementing
    protected $keyType = 'int'; // Set the primary key type

    protected $fillable = [
        'hotelId',
        'maxOccupancy',
        'available',
        'pricePerNight',
        'roomType',
        'bedType',
        'amenities',
        'image',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class, 'hotelId', 'hotelId');
    }
}