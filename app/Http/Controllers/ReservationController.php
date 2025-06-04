<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class ReservationController extends Controller
{
    // POST /api/reservations - Create reservation
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'hotel_id' => 'required|exists:hotels,id',
            'room_id' => 'required|exists:rooms,id',
            'check_in_date' => 'required|date|after:today',
            'check_out_date' => 'required|date|after:check_in_date',
            'adults' => 'required|integer|min:1',
            'children' => 'integer|min:0',
            'special_requests' => 'nullable|string'
        ]);

        // Verify room belongs to hotel
        $room = Room::where('id', $request->room_id)
            ->where('hotel_id', $request->hotel_id)
            ->firstOrFail();

        // Check room availability
        if (!$room->isAvailable($request->check_in_date, $request->check_out_date)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Room is not available for selected dates'
            ], 400);
        }

        // Calculate pricing
        $checkIn = Carbon::parse($request->check_in_date);
        $checkOut = Carbon::parse($request->check_out_date);
        $nights = $checkIn->diffInDays($checkOut);
        $roomRate = $room->roomType->base_price;
        $totalAmount = $nights * $roomRate;

        $reservation = Reservation::create([
            'user_id' => auth()->id(),
            'hotel_id' => $request->hotel_id,
            'room_id' => $request->room_id,
            'check_in_date' => $request->check_in_date,
            'check_out_date' => $request->check_out_date,
            'nights' => $nights,
            'adults' => $request->adults,
            'children' => $request->children ?? 0,
            'room_rate' => $roomRate,
            'total_amount' => $totalAmount,
            'pending_amount' => $totalAmount,
            'special_requests' => $request->special_requests,
            'status' => 'pending',
            'payment_status' => 'pending'
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $reservation->load(['hotel', 'room.roomType']),
            'message' => 'Reservation created successfully'
        ], 201);
    }

    // GET /api/reservations - User's reservations
    public function index(Request $request): JsonResponse
    {
        $query = auth()->user()->reservations()
            ->with(['hotel', 'room.roomType']);
            
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }
        
        if ($request->has('check_in_from')) {
            $query->where('check_in_date', '>=', $request->check_in_from);
        }
        
        if ($request->has('check_in_to')) {
            $query->where('check_in_date', '<=', $request->check_in_to);
        }
        
        $reservations = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 10));

        return response()->json([
            'status' => 'success',
            'data' => $reservations
        ]);
    }

    // GET /api/reservations/{id} - Single reservation
    public function show(Reservation $reservation): JsonResponse
    {
        if ($reservation->user_id !== auth()->id()) {
            abort(403, 'Unauthorized');
        }

        return response()->json([
            'status' => 'success',
            'data' => $reservation->load(['hotel', 'room.roomType', 'payments', 'review'])
        ]);
    }

    // Owner's reservations
    public function ownerReservations(Request $request): JsonResponse
    {
        $query = Reservation::whereHas('hotel', function($query) {
            $query->where('owner_id', auth()->id());
        })->with(['user', 'hotel', 'room.roomType']);
        
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->payment_status);
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

    // PUT /api/reservations/{id} - Update reservation
    public function update(Request $request, Reservation $reservation): JsonResponse
    {
        // Only allow updates for pending reservations
        if (!in_array($reservation->status, ['pending', 'confirmed'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot update reservation with current status'
            ], 400);
        }

        // Check authorization
        if ($reservation->user_id !== auth()->id()) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'check_in_date' => 'sometimes|date|after:today',
            'check_out_date' => 'sometimes|date|after:check_in_date',
            'adults' => 'sometimes|integer|min:1',
            'children' => 'nullable|integer|min:0',
            'special_requests' => 'nullable|string'
        ]);

        // If dates are being updated, check availability
        if (isset($validated['check_in_date']) || isset($validated['check_out_date'])) {
            $checkIn = $validated['check_in_date'] ?? $reservation->check_in_date;
            $checkOut = $validated['check_out_date'] ?? $reservation->check_out_date;
            
            // Temporarily exclude current reservation from availability check
            $isAvailable = !$reservation->room->reservations()
                ->where('id', '!=', $reservation->id)
                ->where('status', '!=', 'cancelled')
                ->where(function($query) use ($checkIn, $checkOut) {
                    $query->whereBetween('check_in_date', [$checkIn, $checkOut])
                          ->orWhereBetween('check_out_date', [$checkIn, $checkOut])
                          ->orWhere(function($q) use ($checkIn, $checkOut) {
                              $q->where('check_in_date', '<=', $checkIn)
                                ->where('check_out_date', '>=', $checkOut);
                          });
                })->exists();

            if (!$isAvailable) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Room is not available for the selected dates'
                ], 400);
            }

            // Recalculate nights and total if dates changed
            if (isset($validated['check_in_date']) || isset($validated['check_out_date'])) {
                $nights = Carbon::parse($checkIn)->diffInDays(Carbon::parse($checkOut));
                $validated['nights'] = $nights;
                $validated['total_amount'] = $nights * $reservation->room_rate;
                $validated['pending_amount'] = $validated['total_amount'] - $reservation->paid_amount;
            }
        }

        $reservation->update($validated);

        return response()->json([
            'status' => 'success',
            'data' => $reservation->load(['hotel', 'room.roomType']),
            'message' => 'Reservation updated successfully'
        ]);
    }

    // DELETE /api/reservations/{id} - Cancel reservation
    public function destroy(Reservation $reservation): JsonResponse
    {
        // Only allow cancellation of pending or confirmed reservations
        if (!in_array($reservation->status, ['pending', 'confirmed'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot cancel reservation with current status'
            ], 400);
        }

        // Check authorization
        if ($reservation->user_id !== auth()->id()) {
            abort(403, 'Unauthorized');
        }

        $reservation->update(['status' => 'cancelled']);

        return response()->json([
            'status' => 'success',
            'message' => 'Reservation cancelled successfully'
        ]);
    }

    // POST /api/reservations/{id}/confirm - Confirm reservation (owner only)
    public function confirm(Reservation $reservation): JsonResponse
    {
        if ($reservation->hotel->owner_id !== auth()->id()) {
            abort(403, 'Unauthorized');
        }

        if ($reservation->status !== 'pending') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only pending reservations can be confirmed'
            ], 400);
        }

        $reservation->update([
            'status' => 'confirmed',
            'confirmed_at' => now()
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Reservation confirmed successfully'
        ]);
    }

    // POST /api/reservations/{id}/check-in - Check in (owner only)
    public function checkIn(Reservation $reservation): JsonResponse
    {
        if ($reservation->hotel->owner_id !== auth()->id()) {
            abort(403, 'Unauthorized');
        }

        if ($reservation->status !== 'confirmed') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only confirmed reservations can be checked in'
            ], 400);
        }

        $reservation->update([
            'status' => 'checked_in',
            'checked_in_at' => now()
        ]);

        // Update room status to occupied
        $reservation->room->update(['status' => 'occupied']);

        return response()->json([
            'status' => 'success',
            'message' => 'Guest checked in successfully'
        ]);
    }

    // POST /api/reservations/{id}/check-out - Check out (owner only)
    public function checkOut(Reservation $reservation): JsonResponse
    {
        if ($reservation->hotel->owner_id !== auth()->id()) {
            abort(403, 'Unauthorized');
        }

        if ($reservation->status !== 'checked_in') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only checked-in reservations can be checked out'
            ], 400);
        }

        $reservation->update([
            'status' => 'completed',
            'checked_out_at' => now()
        ]);

        // Update room status to available
        $reservation->room->update(['status' => 'available']);

        return response()->json([
            'status' => 'success',
            'message' => 'Guest checked out successfully'
        ]);
    }

    // GET /api/reservations/code/{code} - Get reservation by code
    public function getByCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reservation_code' => 'required|string'
        ]);

        $reservation = Reservation::where('reservation_code', $validated['reservation_code'])
            ->with(['user', 'hotel', 'room.roomType', 'payments'])
            ->first();

        if (!$reservation) {
            return response()->json([
                'status' => 'error',
                'message' => 'Reservation not found'
            ], 404);
        }

        // Check if user is authorized (either the guest or the hotel owner)
        if ($reservation->user_id !== auth()->id() && $reservation->hotel->owner_id !== auth()->id()) {
            abort(403, 'Unauthorized');
        }

        return response()->json([
            'status' => 'success',
            'data' => $reservation
        ]);
    }
}