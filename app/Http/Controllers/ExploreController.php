<?php
// app/Http/Controllers/ExploreController.php
namespace App\Http\Controllers;

use App\Models\Explore;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;

class ExploreController extends Controller
{
    protected $cloudinaryService;

    public function __construct(CloudinaryService $cloudinaryService)
    {
        $this->cloudinaryService = $cloudinaryService;
    }

    // Get all explore items
    public function index()
    {
        return response()->json([
            'message' => 'Explore data fetched successfully',
            'data' => Explore::all()
        ]);
    }

    // Create a new explore item
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
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

        $explore = Explore::create([
            'name' => $request->name,
            'description' => $request->description,
            'image' => $uploadResult['url']
        ]);

        return response()->json([
            'message' => 'Explore item created successfully',
            'data' => $explore
        ], 201);
    }
    // update an existing explore item
    public function update(Request $request, $id)
    {
        $explore = Explore::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string',
            'description' => 'nullable|string',
            'image' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        $updateData = $request->only(['name', 'description']);

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

        $explore->update($updateData);
        $explore->refresh();

        return response()->json([
            'message' => 'Explore item updated successfully',
            'data' => $explore
        ]);
    }
}