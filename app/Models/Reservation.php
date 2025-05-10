<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    use HasFactory;

    protected $primaryKey = 'reservationID'; // Set the primary key
    public $incrementing = true; // Ensure the primary key is auto-incrementing
    protected $keyType = 'int'; // Set the primary key type

    protected $fillable = [
        'hotelId',
        'userName',
        'startDate',
        'endDate',
        'amountPeople',
        'imageRoom',
        'floor',
        'status',
        'email',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class, 'hotelId', 'hotelId');
    }
}