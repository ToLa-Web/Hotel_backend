<?php

namespace App\Http\Controllers;

use App\Models\RoomType;
use App\Models\Hotel;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class RoomTypeController extends Controller
{
    protected $cloudinaryService;

    public function __construct(CloudinaryService $cloudinaryService)
    {
        $this->cloudinaryService = $cloudinaryService;
    }

    public function index(Request $request): JsonResponse
    {
        $query = RoomType::with(['hotel']);

        if ($request->has('hotel_id')) {
            $query->where('hotel_id', $request->hotel_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $roomTypes = $query->paginate($request->get('per_page', 15));

        return response()->json($roomTypes);
    }

    public function store(Request $request): JsonResponse
{
    $validated = $request->validate([
        'hotel_id' => 'required|exists:hotels,id',
        'name' => 'required|string|max:255',
        'description' => 'nullable|string',
        'base_price' => 'required|numeric|min:0',
        'capacity' => 'required|integer|min:1',
        'size' => 'nullable|numeric|min:0',
        'amenities' => 'nullable|array',
        'images' => 'nullable|array', // Made nullable since images might not be present
        'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        'status' => 'required|in:active,inactive'
    ]);

    // Handle image uploads if present
    $uploadedImages = [];
    if ($request->hasFile('images')) {
        foreach ($request->file('images') as $index => $image) {
            if ($image) { // Check if image exists at this index
                $uploadResult = $this->cloudinaryService->uploadImage($image->getRealPath());

                if (!$uploadResult['success']) {
                    throw ValidationException::withMessages([
                        'images' => 'Failed to upload image at position ' . ($index + 1) . ': ' . $uploadResult['message']
                    ]);
                }

                // Store with index to maintain order
                $uploadedImages[$index] = $uploadResult['url'];
            }
        }
    }

    // Convert to sequential array maintaining order (fill empty slots with null)
    $finalImages = [];
    for ($i = 0; $i < 4; $i++) {
        $finalImages[] = $uploadedImages[$i] ?? null;
    }

    // Only set images if there are any, otherwise set as empty array
    $validated['images'] = array_values(array_filter($finalImages)); // Remove nulls and reindex
    
    $roomType = RoomType::create($validated);
    $roomType->load('hotel');

    return response()->json($roomType, 201);
}

    public function show(RoomType $roomType): JsonResponse
    {
        $roomType->load(['hotel', 'rooms']);
        return response()->json($roomType);
    }

    public function update(Request $request, RoomType $roomType): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'base_price' => 'sometimes|numeric|min:0',
            'capacity' => 'sometimes|integer|min:1',
            'size' => 'nullable|numeric|min:0',
            'amenities' => 'nullable|array',
            'images' => 'nullable|array',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'existing_images' => 'nullable|array',
            'existing_images.*' => 'nullable|string',
            'deleted_images' => 'nullable|array',
            'deleted_images.*' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive'
        ]);

        // Handle image updates
        $finalImages = [];
        $currentImages = $roomType->images ?? [];

        // Start with existing images
        if ($request->has('existing_images')) {
            foreach ($request->input('existing_images') as $index => $imageUrl) {
                if ($imageUrl) {
                    $finalImages[$index] = $imageUrl;
                }
            }
        } else {
            // If no existing_images specified, keep current images
            foreach ($currentImages as $index => $imageUrl) {
                $finalImages[$index] = $imageUrl;
            }
        }

        // Handle new image uploads
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $index => $image) {
                if ($image) {
                    $uploadResult = $this->cloudinaryService->uploadImage($image->getRealPath());

                    if (!$uploadResult['success']) {
                        throw ValidationException::withMessages([
                            'images' => 'Failed to upload image at position ' . ($index + 1) . ': ' . $uploadResult['message']
                        ]);
                    }

                    // Replace or add new image at specific index
                    $finalImages[$index] = $uploadResult['url'];
                }
            }
        }

        // Handle deleted images
        if ($request->has('deleted_images')) {
            $deletedImages = $request->input('deleted_images');
            foreach ($finalImages as $index => $imageUrl) {
                if (in_array($imageUrl, $deletedImages)) {
                    unset($finalImages[$index]);
                }
            }
        }

        // Convert to sequential array maintaining order (max 4 images)
        $orderedImages = [];
        for ($i = 0; $i < 4; $i++) {
            if (isset($finalImages[$i])) {
                $orderedImages[] = $finalImages[$i];
            }
        }

        $validated['images'] = $orderedImages;

        // Remove the helper fields from validation data
        unset($validated['existing_images'], $validated['deleted_images']);

        $roomType->update($validated);
        $roomType->load('hotel');

        return response()->json($roomType);
    }

    // In RoomTypeController.php
    public function featuredRoomTypes()
    {
        $featuredIds = [1, 2, 3]; // Your specific room type IDs
        return RoomType::with(['hotel'])
            ->whereIn('id', $featuredIds)
            ->where('status', 'active')
            ->limit(3)
            ->get();
    }

    public function destroy(RoomType $roomType): JsonResponse
    {
        // Check if room type has associated rooms
        if ($roomType->rooms()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete room type with associated rooms'
            ], 422);
        }

        $roomType->delete();
        return response()->json(['message' => 'Room type deleted successfully']);
    }

    public function checkAvailability(Request $request, RoomType $roomType): JsonResponse
    {
        $validated = $request->validate([
            'check_in' => 'required|date|after:today',
            'check_out' => 'required|date|after:check_in'
        ]);

        $availableRooms = $roomType->availableRooms(
            $validated['check_in'],
            $validated['check_out']
        )->get();

        return response()->json([
            'room_type' => $roomType,
            'available_rooms' => $availableRooms,
            'available_count' => $availableRooms->count()
        ]);
    }

    public function removeImage(RoomType $roomType, Request $request): JsonResponse
    {
        $request->validate([
            'image_url' => 'required|string'
        ]);

        $images = $roomType->images ?? [];

        if (($key = array_search($request->image_url, $images)) !== false) {
            unset($images[$key]);
            $roomType->update(['images' => array_values($images)]);

            return response()->json([
                'message' => 'Image removed successfully'
            ]);
        }

        return response()->json([
            'message' => 'Image not found'
        ], 404);
    }
}