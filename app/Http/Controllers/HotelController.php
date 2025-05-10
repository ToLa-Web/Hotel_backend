<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;

class HotelController extends Controller
{
    protected $cloudinaryService;

    public function __construct(CloudinaryService $cloudinaryService)
    {
        $this->cloudinaryService = $cloudinaryService;
    }

    // Get all hotels
    public function index()
    {
        return Hotel::all();
    }

    // Create a new hotel or multiple hotels
    public function store(Request $request)
    {
        // If the request is a single hotel (not an array)
        if (!$request->has(0)) {
            $request->validate([
                'hotelName' => 'required|string',
                'amountRoom' => 'required|integer',
                'location' => 'required|string',
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

            // Create hotel with Cloudinary URL
            $hotel = Hotel::create([
                'hotelName' => $request->hotelName,
                'amountRoom' => $request->amountRoom,
                'location' => $request->location,
                'image' => $uploadResult['url']
            ]);

            return response()->json([
                'message' => 'Hotel created successfully',
                'data' => $hotel
            ], 201);
        }

        // If the request is an array of hotels (bulk)
        $request->validate([
            '*.hotelName' => 'required|string',
            '*.amountRoom' => 'required|integer',
            '*.location' => 'required|string',
            '*.image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        $hotels = collect($request->all())->map(function ($hotel) {
            $imageFile = $hotel['image'];
            $uploadResult = $this->cloudinaryService->uploadImage($imageFile->getRealPath());

            if (!$uploadResult['success']) {
                throw new \Exception('Failed to upload image: ' . $uploadResult['message']);
            }

            return Hotel::create([
                'hotelName' => $hotel['hotelName'],
                'amountRoom' => $hotel['amountRoom'],
                'location' => $hotel['location'],
                'image' => $uploadResult['url']
            ]);
        });

        return response()->json([
            'message' => 'Hotels created successfully',
            'data' => $hotels
        ], 201);
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

        $request->validate([
            'hotelName' => 'sometimes|string',
            'amountRoom' => 'sometimes|integer',
            'location' => 'sometimes|string',
            'image' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        $updateData = $request->only(['hotelName', 'amountRoom', 'location']);

        // Log incoming data
        \Log::info('UpdateData:', $updateData);

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
            \Log::info('Image uploaded:', ['url' => $uploadResult['url']]);
        }

        \Log::info('Hotel before update:', $hotel->toArray());
        $hotel->update($updateData);
        $hotel->refresh();
        \Log::info('Hotel after update:', $hotel->toArray());

        return response()->json([
            'message' => 'Hotel updated successfully',
            'data' => $hotel
        ]);
    }

    // Delete a hotel
    public function destroy($id)
    {
        return Hotel::destroy($id);
    }
}