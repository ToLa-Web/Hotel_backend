<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Hotel;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class HotelController extends Controller
{
    protected CloudinaryService $cloudinaryService;

    public function __construct(CloudinaryService $cloudinaryService)
    {
        $this->cloudinaryService = $cloudinaryService;

        // any guest can list / view; everything else requires JWT auth
        $this->middleware('auth:api')->except(['index', 'show']);
    }

    /* -----------------------------------------------------------------
     |  PUBLIC ENDPOINTS
     |---------------------------------------------------------------- */

    /** GET /api/hotels
     *  List ACTIVE hotels + optional filters (city, availability).
     */
    public function index(Request $request)
    {
        $query = Hotel::with(['roomTypes', 'reviews'])
            ->where('status', 'active');

        // ----- Filter by city -------------------------------------------------
        if ($request->filled('city')) {
            $query->where('city', 'like', "%{$request->city}%");
        }

        // ----- Filter by date availability -----------------------------------
        if ($request->filled(['check_in', 'check_out'])) {
            $checkIn  = $request->date('check_in');
            $checkOut = $request->date('check_out');

            $query->whereHas('rooms', function ($q) use ($checkIn, $checkOut) {
                $q->where('status', 'available')
                  ->whereNotIn('id', function ($sub) use ($checkIn, $checkOut) {
                      // rooms that are already booked within the window
                      $sub->select('room_id')
                          ->from('reservations')
                          ->where(function ($w) use ($checkIn, $checkOut) {
                              $w->whereBetween('check_in',  [$checkIn, $checkOut])
                                ->orWhereBetween('check_out', [$checkIn, $checkOut])
                                ->orWhere(function ($v) use ($checkIn, $checkOut) {
                                    // reservation completely covers requested range
                                    $v->where('check_in',  '<=', $checkIn)
                                      ->where('check_out', '>=', $checkOut);
                                });
                          });
                  });
            });
        }

        // pagination (10 per page, keeps query-string params)
        return response()->json([
            'status' => 'success',
            'data'   => $query->paginate(10)->withQueryString(),
        ]);
    }

    /** GET /api/hotels/{id}
     *  Single hotel with relations.
     */
    public function show(int $id)
    {
        $hotel = Hotel::with(['roomTypes', 'reviews.user', 'owner'])
                      ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data'   => $hotel,
        ]);
    }

    /* -----------------------------------------------------------------
     |  OWNER / ADMIN ENDPOINTS
     |---------------------------------------------------------------- */

    /** POST /api/hotels
     *  Create hotel (only Admin / Owner, enforced by routes).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'required|string',
            'address'     => 'required|string',
            'city'        => 'required|string|max:255',
            'state'       => 'required|string|max:255',
            'country'     => 'required|string|max:255',
            'postal_code' => 'required|string|max:20',
            'latitude'    => 'nullable|numeric',
            'longitude'   => 'nullable|numeric',
            'phone'       => 'required|string|max:20',
            'email'       => 'required|email|max:255',
            'website'     => 'nullable|url|max:255',
            'amenities'   => 'nullable|array',
            'images'      => 'required|array',
            'images.*'    => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'status'      => 'sometimes|in:active,inactive,under_review',
        ]);

        /* ---------- upload images to Cloudinary --------------------------- */
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

        /* ---------- create hotel ------------------------------------------ */
        $hotel = Hotel::create([
            'owner_id'     => auth()->id(),   // assumes JWT auth
            'name'         => $validated['name'],
            'slug'         => Str::slug($validated['name']),
            'description'  => $validated['description'],
            'address'      => $validated['address'],
            'city'         => $validated['city'],
            'state'        => $validated['state'],
            'country'      => $validated['country'],
            'postal_code'  => $validated['postal_code'],
            'latitude'     => $validated['latitude'] ?? null,
            'longitude'    => $validated['longitude'] ?? null,
            'phone'        => $validated['phone'],
            'email'        => $validated['email'],
            'website'      => $validated['website'] ?? null,
            'amenities'    => $validated['amenities'] ?? null,
            'images'       => $validated['images'] ?? [],
            'status'       => $validated['status'] ?? 'active',
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Hotel created successfully',
            'data'    => $hotel,
        ], 201);
    }

    /** PUT /api/hotels/{id}
     *  Update hotel.
     */
    public function update(Request $request, int $id)
    {
        $hotel = Hotel::findOrFail($id);

        // Only owner OR admin may update
        if (auth()->id() !== $hotel->owner_id && !auth()->user()->isAdmin()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized',
            ], 403);
        }

        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'address'     => 'sometimes|string',
            'city'        => 'sometimes|string|max:255',
            'state'       => 'sometimes|string|max:255',
            'country'     => 'sometimes|string|max:255',
            'postal_code' => 'sometimes|string|max:20',
            'latitude'    => 'nullable|numeric',
            'longitude'   => 'nullable|numeric',
            'phone'       => 'sometimes|string|max:20',
            'email'       => 'sometimes|email|max:255',
            'website'     => 'nullable|url|max:255',
            'amenities'   => 'nullable|array',
            'images'      => 'sometimes|array',
            'images.*'    => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'status'      => 'sometimes|in:active,inactive,under_review',
        ]);

        /* ---------- handle new images ------------------------------------- */
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
            $existingImages = $hotel->images ?? [];
            $validated['images'] = array_merge($existingImages, $uploadedImages);
        }

        /* ---------- prepare update data ----------------------------------- */
        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $hotel->update($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Hotel updated successfully',
            'data'    => $hotel,
        ]);
    }

    /* -----------------------------------------------------------------
     |  OWNER DASHBOARD
     |---------------------------------------------------------------- */

    /** GET /api/my-hotels
     *  List hotels belonging to the logged-in owner.
     */
    public function myHotels()
    {
        $hotels = auth()->user()
                        ->hotels()
                        ->with(['roomTypes', 'rooms'])
                        ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $hotels,
        ]);
    }

    /** POST /api/hotels/{id}/remove-image
     *  Remove specific image from hotel.
     */
    public function removeImage(Request $request, int $id)
    {
        $hotel = Hotel::findOrFail($id);

        // Only owner OR admin may update
        if (auth()->id() !== $hotel->owner_id && !auth()->user()->isAdmin()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized',
            ], 403);
        }

        $request->validate([
            'image_url' => 'required|string'
        ]);

        $images = $hotel->images ?? [];

        if (($key = array_search($request->image_url, $images)) !== false) {
            unset($images[$key]);
            $hotel->update(['images' => array_values($images)]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Image removed successfully'
            ]);
        }

        return response()->json([
            'status'  => 'error',
            'message' => 'Image not found'
        ], 404);
    }
}