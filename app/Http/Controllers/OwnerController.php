<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Reservation;
use App\Models\Payment;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OwnerController extends Controller
{
    protected CloudinaryService $cloudinaryService;

    public function __construct(CloudinaryService $cloudinaryService)
    {
        $this->cloudinaryService = $cloudinaryService;
        $this->middleware('auth:api');
        $this->middleware('role:Owner');
    }

    /* -----------------------------------------------------------------
     |  DASHBOARD
     |---------------------------------------------------------------- */

    /**
     * GET /api/owner/dashboard
     * Owner dashboard with key metrics and recent activities
     */
    public function dashboard(): JsonResponse
    {
        $ownerId = auth()->id();
        
        // Get owner's hotels
        $hotels = Hotel::where('owner_id', $ownerId)->get();
        $hotelIds = $hotels->pluck('id');

        // Key metrics
        $totalHotels = $hotels->count();
        $totalRooms = Room::whereIn('hotel_id', $hotelIds)->count();
        $activeHotels = $hotels->where('status', 'active')->count();
        
        // Revenue metrics (last 30 days)
        $thirtyDaysAgo = Carbon::now()->subDays(30);
        $totalRevenue = Payment::whereHas('reservation', function($query) use ($hotelIds) {
            $query->whereIn('hotel_id', $hotelIds);
        })
        ->where('status', 'completed')
        ->where('created_at', '>=', $thirtyDaysAgo)
        ->sum('amount');

        // Reservation metrics (last 30 days)
        $totalReservations = Reservation::whereIn('hotel_id', $hotelIds)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->count();

        $pendingReservations = Reservation::whereIn('hotel_id', $hotelIds)
            ->where('status', 'pending')
            ->count();

        $checkedInGuests = Reservation::whereIn('hotel_id', $hotelIds)
            ->where('status', 'checked_in')
            ->count();

        // Occupancy rate (current)
        $occupiedRooms = Room::whereIn('hotel_id', $hotelIds)
            ->where('status', 'occupied')
            ->count();
        $occupancyRate = $totalRooms > 0 ? round(($occupiedRooms / $totalRooms) * 100, 2) : 0;

        // Recent reservations
        $recentReservations = Reservation::whereIn('hotel_id', $hotelIds)
            ->with(['user', 'hotel', 'room.roomType'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Revenue trend (last 7 days)
        $revenueTrend = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $dailyRevenue = Payment::whereHas('reservation', function($query) use ($hotelIds) {
                $query->whereIn('hotel_id', $hotelIds);
            })
            ->where('status', 'completed')
            ->whereDate('created_at', $date)
            ->sum('amount');
            
            $revenueTrend[] = [
                'date' => $date->format('Y-m-d'),
                'revenue' => $dailyRevenue
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'metrics' => [
                    'total_hotels' => $totalHotels,
                    'active_hotels' => $activeHotels,
                    'total_rooms' => $totalRooms,
                    'total_revenue' => $totalRevenue,
                    'total_reservations' => $totalReservations,
                    'pending_reservations' => $pendingReservations,
                    'checked_in_guests' => $checkedInGuests,
                    'occupancy_rate' => $occupancyRate
                ],
                'recent_reservations' => $recentReservations,
                'revenue_trend' => $revenueTrend
            ]
        ]);
    }

    /* -----------------------------------------------------------------
     |  HOTEL MANAGEMENT
     |---------------------------------------------------------------- */

    /**
     * GET /api/owner/hotels
     * Get all hotels belonging to the authenticated owner
     */
    public function getHotels(Request $request): JsonResponse
    {
        $query = Hotel::where('owner_id', auth()->id())
            ->with(['roomTypes', 'rooms']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('city', 'like', "%{$request->search}%")
                  ->orWhere('address', 'like', "%{$request->search}%");
            });
        }

        $hotels = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 10));

        return response()->json([
            'status' => 'success',
            'data' => $hotels
        ]);
    }

    /**
     * GET /api/owner/hotels/{hotel}
     * Get detailed information about a specific hotel
     */
    public function getHotelDetailById(Hotel $hotel): JsonResponse
    {
        // Ensure hotel belongs to authenticated owner
        if ($hotel->owner_id !== auth()->id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 403);
        }

        // Load all related data with correct relationships
        $hotel->load([
            'roomTypes' => function($query) {
                $query->orderBy('base_price');
            },
            'rooms' => function($query) {
                $query->orderBy('room_number');
            },
            'reservations' => function($query) {
                $query->where('status', '!=', 'cancelled')
                      ->with(['payments' => function($paymentQuery) {
                          $paymentQuery->where('status', 'completed')
                                       ->orderBy('created_at', 'desc')
                                       ->limit(5);
                      }])
                      ->orderBy('check_in_date', 'desc')
                      ->limit(5);
            }
        ]);

        // Calculate statistics
        $stats = [
            'total_rooms' => $hotel->rooms->count(),
            'occupied_rooms' => $hotel->rooms->where('status', 'occupied')->count(),
            'available_rooms' => $hotel->rooms->where('status', 'available')->count(),
            'room_type_count' => $hotel->roomTypes->count(),
            'active_reservations' => $hotel->reservations->count(),
        ];

        // Calculate 30-day metrics
        $thirtyDaysAgo = Carbon::now()->subDays(30);
        
        $stats['thirty_days_revenue'] = Payment::whereHas('reservation', function($query) use ($hotel) {
            $query->where('hotel_id', $hotel->id);
        })
        ->where('status', 'completed')
        ->where('created_at', '>=', $thirtyDaysAgo)
        ->sum('amount');

        $occupiedRoomsCount = DB::table('reservations')
            ->select(DB::raw('COUNT(DISTINCT room_id) as occupied_count'))
            ->where('hotel_id', $hotel->id)
            ->where('status', 'checked_in')
            ->where('check_in_date', '>=', $thirtyDaysAgo)
            ->first();

        $stats['thirty_days_occupancy_rate'] = $stats['total_rooms'] > 0 
            ? round(($occupiedRoomsCount->occupied_count / $stats['total_rooms']) * 100, 2)
            : 0;

        return response()->json([
            'status' => 'success',
            'data' => [
                'hotel' => $hotel,
                'stats' => $stats
            ]
        ]);
    }

    /**
     * POST /api/owner/hotels
     * Create a new hotel
     */
    public function createHotel(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'address' => 'required|string',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'postal_code' => 'required|string|max:20',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'phone' => 'required|string|max:20',
            'email' => 'required|email|max:255',
            'website' => 'nullable|url|max:255',
            'amenities' => 'nullable|array',
            'images' => 'required|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'status' => 'sometimes|in:active,inactive,under_review',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Upload images to Cloudinary
        $imageUrls = [];
        foreach ($request->file('images', []) as $image) {
            $upload = $this->cloudinaryService->uploadImage($image->getRealPath());
            if (!$upload['success']) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to upload images',
                    'error' => $upload['message'],
                ], 500);
            }
            $imageUrls[] = $upload['url'];
        }

        // Create hotel
        $hotel = Hotel::create([
            'owner_id' => auth()->id(),
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'description' => $request->description,
            'address' => $request->address,
            'city' => $request->city,
            'state' => $request->state,
            'country' => $request->country,
            'postal_code' => $request->postal_code,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'phone' => $request->phone,
            'email' => $request->email,
            'website' => $request->website,
            'amenities' => json_encode($request->amenities),
            'images' => json_encode($imageUrls),
            'status' => $request->status ?? 'active',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Hotel created successfully',
            'data' => $hotel->load(['roomTypes', 'rooms']),
        ], 201);
    }

    /**
     * PUT /api/owner/hotels/{hotel}
     * Update hotel
     */
    public function updateHotel(Request $request, Hotel $hotel): JsonResponse
    {
        // Ensure hotel belongs to authenticated owner
        if ($hotel->owner_id !== auth()->id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'address' => 'sometimes|string',
            'city' => 'sometimes|string|max:255',
            'state' => 'sometimes|string|max:255',
            'country' => 'sometimes|string|max:255',
            'postal_code' => 'sometimes|string|max:20',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'phone' => 'sometimes|string|max:20',
            'email' => 'sometimes|email|max:255',
            'website' => 'nullable|url|max:255',
            'amenities' => 'nullable|array',
            'images' => 'sometimes|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'status' => 'sometimes|in:active,inactive,under_review',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Prepare update data
        $data = $request->only([
            'name', 'description', 'address', 'city', 'state', 'country',
            'postal_code', 'latitude', 'longitude', 'phone', 'email',
            'website', 'amenities', 'status',
        ]);

        if ($request->filled('name')) {
            $data['slug'] = Str::slug($request->name);
        }

        if ($request->has('amenities')) {
            $data['amenities'] = json_encode($request->amenities);
        }

        // Handle new images
        if ($request->hasFile('images')) {
            $currentImages = json_decode($hotel->images, true) ?? [];
            foreach ($request->file('images') as $image) {
                $upload = $this->cloudinaryService->uploadImage($image->getRealPath());
                if (!$upload['success']) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Failed to upload images',
                        'error' => $upload['message'],
                    ], 500);
                }
                $currentImages[] = $upload['url'];
            }
            $data['images'] = json_encode($currentImages);
        }

        $hotel->update($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Hotel updated successfully',
            'data' => $hotel->load(['roomTypes', 'rooms']),
        ]);
    }

    /**
     * DELETE /api/owner/hotels/{hotel}
     * Delete hotel (soft delete or hard delete based on business logic)
     */
    public function deleteHotel(Hotel $hotel): JsonResponse
    {
        // Ensure hotel belongs to authenticated owner
        if ($hotel->owner_id !== auth()->id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 403);
        }

        // Check if hotel has active reservations
        $activeReservations = $hotel->reservations()
            ->whereIn('status', ['confirmed', 'checked_in'])
            ->count();

        if ($activeReservations > 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete hotel with active reservations',
            ], 422);
        }

        // Soft delete by setting status to inactive
        $hotel->update(['status' => 'inactive']);

        return response()->json([
            'status' => 'success',
            'message' => 'Hotel deleted successfully',
        ]);
    }

    /**
     * POST /api/owner/hotels/{hotel}/remove-image
     * Remove specific image from hotel
     */
    public function removeHotelImage(Request $request, Hotel $hotel): JsonResponse
    {
        // Ensure hotel belongs to authenticated owner
        if ($hotel->owner_id !== auth()->id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 403);
        }

        $request->validate([
            'image_url' => 'required|string'
        ]);

        $images = json_decode($hotel->images, true) ?? [];

        if (($key = array_search($request->image_url, $images)) !== false) {
            unset($images[$key]);
            $hotel->update(['images' => json_encode(array_values($images))]);

            return response()->json([
                'status' => 'success',
                'message' => 'Image removed successfully'
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Image not found'
        ], 404);
    }

    /* -----------------------------------------------------------------
     |  ANALYTICS
     |---------------------------------------------------------------- */

    /**
     * GET /api/owner/analytics
     * Get detailed analytics for owner's hotels
     */
    public function getAnalytics(Request $request): JsonResponse
    {
        $ownerId = auth()->id();
        $period = $request->get('period', '30'); // days
        $startDate = Carbon::now()->subDays($period);
        
        $hotelIds = Hotel::where('owner_id', $ownerId)->pluck('id');

        // Revenue analytics
        $revenueData = Payment::whereHas('reservation', function($query) use ($hotelIds) {
            $query->whereIn('hotel_id', $hotelIds);
        })
        ->where('status', 'completed')
        ->where('created_at', '>=', $startDate)
        ->selectRaw('DATE(created_at) as date, SUM(amount) as revenue')
        ->groupBy('date')
        ->orderBy('date')
        ->get();

        // Booking analytics
        $bookingData = Reservation::whereIn('hotel_id', $hotelIds)
            ->where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as bookings, status')
            ->groupBy('date', 'status')
            ->orderBy('date')
            ->get();

        // Hotel performance
        $hotelPerformance = Hotel::where('owner_id', $ownerId)
            ->withCount(['reservations' => function($query) use ($startDate) {
                $query->where('created_at', '>=', $startDate);
            }])
            ->with(['reservations' => function($query) use ($startDate) {
                $query->where('created_at', '>=', $startDate)
                      ->where('status', 'completed');
            }])
            ->get()
            ->map(function($hotel) {
                $totalRevenue = $hotel->reservations->sum('total_amount');
                return [
                    'id' => $hotel->id,
                    'name' => $hotel->name,
                    'reservations_count' => $hotel->reservations_count,
                    'total_revenue' => $totalRevenue,
                    'average_booking_value' => $hotel->reservations_count > 0 
                        ? round($totalRevenue / $hotel->reservations_count, 2) 
                        : 0
                ];
            });

        // Room type performance
        $roomTypePerformance = RoomType::whereHas('hotel', function($query) use ($ownerId) {
            $query->where('owner_id', $ownerId);
        })
        ->withCount(['reservations' => function($query) use ($startDate) {
            $query->whereHas('reservation', function($q) use ($startDate) {
                $q->where('created_at', '>=', $startDate);
            });
        }])
        ->get();

        // Occupancy trends
        $occupancyData = [];
        for ($i = $period - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $totalRooms = Room::whereIn('hotel_id', $hotelIds)->count();
            $occupiedRooms = Reservation::whereIn('hotel_id', $hotelIds)
                ->where('status', 'checked_in')
                ->whereDate('check_in_date', '<=', $date)
                ->whereDate('check_out_date', '>', $date)
                ->count();
            
            $occupancyData[] = [
                'date' => $date->format('Y-m-d'),
                'occupancy_rate' => $totalRooms > 0 ? round(($occupiedRooms / $totalRooms) * 100, 2) : 0
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'revenue_data' => $revenueData,
                'booking_data' => $bookingData,
                'hotel_performance' => $hotelPerformance,
                'room_type_performance' => $roomTypePerformance,
                'occupancy_data' => $occupancyData,
                'period' => $period
            ]
        ]);
    }

    /* -----------------------------------------------------------------
     |  ADDITIONAL HELPER METHODS FOR OWNER-SPECIFIC DATA
     |---------------------------------------------------------------- */

    /**
     * Get owner's reservations (called by ReservationController)
     */
    public function getOwnerReservations(Request $request): JsonResponse
    {
        $query = Reservation::whereHas('hotel', function($query) {
            $query->where('owner_id', auth()->id());
        })->with(['user', 'hotel', 'room.roomType']);
        
        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }
        
        if ($request->has('hotel_id')) {
            $query->where('hotel_id', $request->hotel_id);
        }
        
        if ($request->has('check_in_from')) {
            $query->where('check_in_date', '>=', $request->check_in_from);
        }
        
        if ($request->has('check_in_to')) {
            $query->where('check_in_date', '<=', $request->check_in_to);
        }
        
        $reservations = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'status' => 'success',
            'data' => $reservations
        ]);
    }

    /**
     * Get owner's payments (called by PaymentController)
     */
    public function getOwnerPayments(Request $request): JsonResponse
    {
        $query = Payment::whereHas('reservation.hotel', function($query) {
            $query->where('owner_id', auth()->id());
        })->with(['reservation.user', 'reservation.hotel']);
        
        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->has('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }
        
        if ($request->has('hotel_id')) {
            $query->whereHas('reservation', function($q) use ($request) {
                $q->where('hotel_id', $request->hotel_id);
            });
        }
        
        if ($request->has('from_date')) {
            $query->where('created_at', '>=', $request->from_date);
        }
        
        if ($request->has('to_date')) {
            $query->where('created_at', '<=', $request->to_date);
        }
        
        $payments = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));
        
        return response()->json([
            'status' => 'success',
            'data' => $payments
        ]);
    }

    /**
     * Get owner's rooms (called by RoomController)
     */
    public function getOwnerRooms(Request $request): JsonResponse
    {
        $query = Room::whereHas('hotel', function($query) {
            $query->where('owner_id', auth()->id());
        })->with(['hotel', 'roomType']);
        
        // Apply filters
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
        
        $rooms = $query->orderBy('hotel_id')
            ->orderBy('floor')
            ->orderBy('room_number')
            ->paginate($request->get('per_page', 15));
        
        return response()->json([
            'status' => 'success',
            'data' => $rooms
        ]);
    }

    /**
     * Get owner's room types (called by RoomTypeController)
     */
    public function getOwnerRoomTypes(Request $request): JsonResponse
    {
        $query = RoomType::whereHas('hotel', function($query) {
            $query->where('owner_id', auth()->id());
        })->with(['hotel']);
        
        // Apply filters
        if ($request->has('hotel_id')) {
            $query->where('hotel_id', $request->hotel_id);
        }
        
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        $roomTypes = $query->orderBy('hotel_id')
            ->orderBy('name')
            ->paginate($request->get('per_page', 15));
        
        return response()->json([
            'status' => 'success',
            'data' => $roomTypes
        ]);
    }
}