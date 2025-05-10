<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Hotel extends Model
{
    use HasFactory;

    protected $primaryKey = 'hotelId'; // Set the primary key
    public $incrementing = true; // Ensure the primary key is auto-incrementing
    protected $keyType = 'int'; // Set the primary key type

    protected $fillable = [
        'hotelName',
        'amountRoom',
        'location',
        'image',
    ];

    // Define relationships
    public function rooms()
    {
        return $this->hasMany(Room::class, 'hotelId', 'hotelId');
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class, 'hotelId', 'hotelId');
    }
}