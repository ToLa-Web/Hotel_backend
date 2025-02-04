<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use Illuminate\Http\Request;

class HotelController extends Controller
{
    // Get all hotels
    public function index()
    {
        return Hotel::all();
    }

    // Create a new hotel
    public function store(Request $request)
    {
        $request->validate([
            'reservationID' => 'required|exists:reservations,reservationID',
            'roomID' => 'required|exists:rooms,roomId',
            'hotelName' => 'required|string',
            'amountRoom' => 'required|integer',
            'location' => 'required|string',
            'image' => 'nullable|string',
        ]);

        return Hotel::create($request->all());
    }

    // Get a single hotel
    public function show($id)
    {
        return Hotel::findOrFail($id);
    }

    // Update a hotel
    public function update(Request $request, $id)
    {
        $hotel = Hotel::findOrFail($id);
        $hotel->update($request->all());
        return $hotel;
    }

    // Delete a hotel
    public function destroy($id)
    {
        return Hotel::destroy($id);
    }
}