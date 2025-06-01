<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\Hotel;
use App\Models\RoomType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RoomController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Room::with(['hotel', 'roomType']);
        
        if ($request->has('hotel_id')) {
            $query->where('hotel_id', $request->hotel_id);
        }
        
        if ($request->has('room_type_id')) {
            $query->where('room_type_id', $request->room_type_id);
        }
        
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->has('floor')) {
            $query->where('floor', $request->floor);
        }
        
        $rooms = $query->paginate($request->get('per_page', 15));
        
        return response()->json($rooms);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hotel_id' => 'required|exists:hotels,id',
            'room_type_id' => 'required|exists:room_types,id',
            'room_number' => 'required|string|max:10',
            'floor' => 'nullable|integer|min:0',
            'status' => 'required|in:available,occupied,maintenance,out_of_order',
            'notes' => 'nullable|string'
        ]);

        // Check if room number already exists for this hotel
        $existingRoom = Room::where('hotel_id', $validated['hotel_id'])
            ->where('room_number', $validated['room_number'])
            ->first();

        if ($existingRoom) {
            return response()->json([
                'message' => 'Room number already exists for this hotel'
            ], 422);
        }

        // Verify room type belongs to the hotel
        $roomType = RoomType::where('id', $validated['room_type_id'])
            ->where('hotel_id', $validated['hotel_id'])
            ->first();

        if (!$roomType) {
            return response()->json([
                'message' => 'Room type does not belong to the specified hotel'
            ], 422);
        }

        $room = Room::create($validated);
        $room->load(['hotel', 'roomType']);

        return response()->json($room, 201);
    }

    public function show(Room $room): JsonResponse
    {
        $room->load(['hotel', 'roomType', 'reservations' => function($query) {
            $query->where('status', '!=', 'cancelled')
                  ->orderBy('check_in_date', 'desc');
        }]);
        
        return response()->json($room);
    }

    public function update(Request $request, Room $room): JsonResponse
    {
        $validated = $request->validate([
            'room_number' => 'sometimes|string|max:10',
            'floor' => 'nullable|integer|min:0',
            'status' => 'sometimes|in:available,occupied,maintenance,out_of_order',
            'notes' => 'nullable|string'
        ]);

        // Check if room number already exists for this hotel (excluding current room)
        if (isset($validated['room_number'])) {
            $existingRoom = Room::where('hotel_id', $room->hotel_id)
                ->where('room_number', $validated['room_number'])
                ->where('id', '!=', $room->id)
                ->first();

            if ($existingRoom) {
                return response()->json([
                    'message' => 'Room number already exists for this hotel'
                ], 422);
            }
        }

        $room->update($validated);
        $room->load(['hotel', 'roomType']);

        return response()->json($room);
    }

    public function destroy(Room $room): JsonResponse
    {
        // Check if room has active reservations
        $activeReservations = $room->reservations()
            ->whereIn('status', ['confirmed', 'checked_in'])
            ->count();

        if ($activeReservations > 0) {
            return response()->json([
                'message' => 'Cannot delete room with active reservations'
            ], 422);
        }

        $room->delete();
        return response()->json(['message' => 'Room deleted successfully']);
    }

    public function checkAvailability(Request $request, Room $room): JsonResponse
    {
        $validated = $request->validate([
            'check_in' => 'required|date|after:today',
            'check_out' => 'required|date|after:check_in'
        ]);

        $isAvailable = $room->isAvailable(
            $validated['check_in'], 
            $validated['check_out']
        );

        return response()->json([
            'room' => $room,
            'is_available' => $isAvailable,
            'check_in' => $validated['check_in'],
            'check_out' => $validated['check_out']
        ]);
    }

    public function updateStatus(Request $request, Room $room): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:available,occupied,maintenance,out_of_order',
            'notes' => 'nullable|string'
        ]);

        $room->update($validated);

        return response()->json([
            'message' => 'Room status updated successfully',
            'room' => $room
        ]);
    }
}