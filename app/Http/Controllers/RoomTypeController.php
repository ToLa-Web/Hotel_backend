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
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'status' => 'required|in:active,inactive'
        ]);

        // Handle image uploads if present
        if ($request->hasFile('images')) {
            $uploadedImages = [];
            foreach ($request->file('images') as $image) {
                $uploadResult = $this->cloudinaryService->uploadImage($image->getRealPath());
                
                if (!$uploadResult['success']) {
                    throw ValidationException::withMessages([
                        'images' => 'Failed to upload one or more images: ' . $uploadResult['message']
                    ]);
                }
                
                $uploadedImages[] = $uploadResult['url'];
            }
            $validated['images'] = $uploadedImages;
        }

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
            'images.*' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
            'status' => 'sometimes|in:active,inactive'
        ]);

        // Handle image uploads if present
        if ($request->hasFile('images')) {
            $uploadedImages = [];
            foreach ($request->file('images') as $image) {
                $uploadResult = $this->cloudinaryService->uploadImage($image->getRealPath());
                
                if (!$uploadResult['success']) {
                    throw ValidationException::withMessages([
                        'images' => 'Failed to upload one or more images: ' . $uploadResult['message']
                    ]);
                }
                
                $uploadedImages[] = $uploadResult['url'];
            }
            
            // Merge new images with existing ones if they exist
            $existingImages = $roomType->images ?? [];
            $validated['images'] = array_merge($existingImages, $uploadedImages);
        }

        $roomType->update($validated);
        $roomType->load('hotel');

        return response()->json($roomType);
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