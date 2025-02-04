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
        'reservationID',
        'roomID',
        'hotelName',
        'amountRoom',
        'location',
        'image',
    ];

    // Define relationships
    public function reservation()
    {
        return $this->belongsTo(Reservation::class, 'reservationID', 'reservationID');
    }

    public function room()
    {
        return $this->belongsTo(Room::class, 'roomID', 'roomId');
    }
}