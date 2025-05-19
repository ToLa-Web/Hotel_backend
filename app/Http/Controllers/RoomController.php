<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    protected $cloudinaryService;

    public function __construct(CloudinaryService $cloudinaryService)
    {
        $this->cloudinaryService = $cloudinaryService;
    }

    // Get all rooms
    public function index()
    {
        return Room::all();
    }

    // Create a new room
    public function store(Request $request)
    {
        $request->validate([
            'hotelId' => 'required|exists:hotels,hotelId',
            'maxOccupancy' => 'required|integer',
            'available' => 'required|boolean',
            'pricePerNight' => 'required|numeric',
            'roomType' => 'required|string',
            'bedType' => 'required|string',
            'amenities' => 'nullable|string',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        // Upload image to Cloudinary
        $imageFile = $request->file('image');
        $uploadResult = $this->cloudinaryService->uploadImage($imageFile->getRealPath());

        if (!$uploadResult['success']) {
            return response()->json([
                'message' => 'Failed to upload image',
                'error' => $uploadResult['message']
            ], 500);
        }

        $room = Room::create([
            'hotelId' => $request->hotelId,
            'maxOccupancy' => $request->maxOccupancy,
            'available' => $request->available,
            'pricePerNight' => $request->pricePerNight,
            'roomType' => $request->roomType,
            'bedType' => $request->bedType,
            'amenities' => $request->amenities,
            'image' => $uploadResult['url'],
        ]);

        return response()->json([
            'message' => 'Room created successfully',
            'data' => $room
        ], 201);
    }

    // Get a single room
    public function show($id)
    {
        return Room::findOrFail($id);
    }
    // Filter rooms by IDs
    public function filterByIds(Request $request)
    {
        $ids = explode(',', $request->query('ids', ''));
        return Room::whereIn('roomId', $ids)->get(); // Use 'roomId' if that's your PK
    }

    // Update a room
    public function update(Request $request, $id)
    {
        $room = Room::findOrFail($id);

        $request->validate([
            'hotelId' => 'sometimes|exists:hotels,hotelId',
            'maxOccupancy' => 'sometimes|integer',
            'available' => 'sometimes|boolean',
            'pricePerNight' => 'sometimes|numeric',
            'roomType' => 'sometimes|string',
            'bedType' => 'sometimes|string',
            'amenities' => 'nullable|string',
            'image' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        $updateData = $request->only([
            'hotelId', 'maxOccupancy', 'available', 'pricePerNight',
            'roomType', 'bedType', 'amenities'
        ]);

        // Handle image upload if provided
        if ($request->hasFile('image')) {
            $imageFile = $request->file('image');
            $uploadResult = $this->cloudinaryService->uploadImage($imageFile->getRealPath());

            if (!$uploadResult['success']) {
                return response()->json([
                    'message' => 'Failed to upload image',
                    'error' => $uploadResult['message']
                ], 500);
            }

            $updateData['image'] = $uploadResult['url'];
        }

        $room->update($updateData);
        $room->refresh();

        return response()->json([
            'message' => 'Room updated successfully',
            'data' => $room
        ]);
    }

    // Delete a room
    public function destroy($id)
    {
        return Room::destroy($id);
    }

    
}