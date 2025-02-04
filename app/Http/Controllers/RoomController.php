<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    // Get all rooms
    public function index()
    {
        return Room::all();
    }

    // Create a new room
    public function store(Request $request)
    {
        $request->validate([
            'maxOccupancy' => 'required|integer',
            'available' => 'required|boolean',
            'pricePerNight' => 'required|numeric',
            'roomType' => 'required|string',
            'bedType' => 'required|string',
            'amenities' => 'nullable|string',
        ]);

        return Room::create($request->all());
    }

    // Get a single room
    public function show($id)
    {
        return Room::findOrFail($id);
    }

    // Update a room
    public function update(Request $request, $id)
    {
        $room = Room::findOrFail($id);
        $room->update($request->all());
        return $room;
    }

    // Delete a room
    public function destroy($id)
    {
        return Room::destroy($id);
    }
}