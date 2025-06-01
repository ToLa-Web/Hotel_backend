<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

   protected $fillable = [
        'name', 'email', 'password', 'role', 'phone', 'address', 'date_of_birth', 'status'
    ];

    protected $hidden = ['password', 'remember_token'];

    // Relationships
    public function hotels()
    {
        return $this->hasMany(Hotel::class, 'owner_id');
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    // Permission checks
    public function isAdmin()
    {
        return $this->role === 'Admin';
    }

    public function isOwner()
    {
        return $this->role === 'Owner';
    }

    public function isUser()
    {
        return $this->role === 'User';
    }

    public function canManageHotel($hotelId)
    {
        if ($this->isAdmin()) return true;
        if ($this->isOwner()) {
            return $this->hotels()->where('id', $hotelId)->exists();
        }
        return false;
    }

     /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return ['role' => $this->role];
    }
}
