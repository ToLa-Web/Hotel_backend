<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Explore extends Model
{
    use HasFactory;

    protected $primaryKey = 'exploreId';

    protected $fillable = [
        'name',
        'image',
        'description',
    ];
}