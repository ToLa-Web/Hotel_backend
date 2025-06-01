<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Room;
use App\Models\Hotel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class ReservationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Reservation::with(['user', 'hotel', 'room.roomType']);
        
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        
        if ($request->has('hotel_id')) {
            $query->where('hotel_id', $request->hotel_id);
        }
        
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
        
        return response()->json($reservations);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'hotel_id' => 'required|exists:hotels,id',
            'room_id' => 'required|exists:rooms,id',
            'check_in_date' => 'required|date|after:today',
            'check_out_date' => 'required|date|after:check_in_date',
            'adults' => 'required|integer|min:1',
            'children' => 'nullable|integer|min:0',
            'room_rate' => 'required|numeric|min:0',
            'special_requests' => 'nullable|string'
        ]);

        // Verify room belongs to hotel
        $room = Room::where('id', $validated['room_id'])
            ->where('hotel_id', $validated['hotel_id'])
            ->first();

        if (!$room) {
            return response()->json([
                'message' => 'Room does not belong to the specified hotel'
            ], 422);
        }

        // Check room availability
        if (!$room->isAvailable($validated['check_in_date'], $validated['check_out_date'])) {
            return response()->json([
                'message' => 'Room is not available for the selected dates'
            ], 422);
        }

        // Calculate nights and total amount
        $checkIn = Carbon::parse($validated['check_in_date']);
        $checkOut = Carbon::parse($validated['check_out_date']);
        $nights = $checkIn->diffInDays($checkOut);
        $totalAmount = $nights * $validated['room_rate'];

        $reservation = Reservation::create(array_merge($validated, [
            'nights' => $nights,
            'total_amount' => $totalAmount,
            'paid_amount' => 0,
            'pending_amount' => $totalAmount,
            'status' => 'pending',
            'payment_status' => 'pending'
        ]));

        $reservation->load(['user', 'hotel', 'room.roomType']);

        return response()->json($reservation, 201);
    }

    public function show(Reservation $reservation): JsonResponse
    {
        $reservation->load(['user', 'hotel', 'room.roomType', 'payments', 'review']);
        return response()->json($reservation);
    }

    public function update(Request $request, Reservation $reservation): JsonResponse
    {
        // Only allow updates for pending reservations
        if (!in_array($reservation->status, ['pending', 'confirmed'])) {
            return response()->json([
                'message' => 'Cannot update reservation with current status'
            ], 422);
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
                    'message' => 'Room is not available for the selected dates'
                ], 422);
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
        $reservation->load(['user', 'hotel', 'room.roomType']);

        return response()->json($reservation);
    }

    public function destroy(Reservation $reservation): JsonResponse
    {
        // Only allow cancellation of pending or confirmed reservations
        if (!in_array($reservation->status, ['pending', 'confirmed'])) {
            return response()->json([
                'message' => 'Cannot cancel reservation with current status'
            ], 422);
        }

        $reservation->update(['status' => 'cancelled']);

        return response()->json([
            'message' => 'Reservation cancelled successfully',
            'reservation' => $reservation
        ]);
    }

    public function confirm(Reservation $reservation): JsonResponse
    {
        if ($reservation->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending reservations can be confirmed'
            ], 422);
        }

        $reservation->update([
            'status' => 'confirmed',
            'confirmed_at' => now()
        ]);

        return response()->json([
            'message' => 'Reservation confirmed successfully',
            'reservation' => $reservation
        ]);
    }

    public function checkIn(Reservation $reservation): JsonResponse
    {
        if ($reservation->status !== 'confirmed') {
            return response()->json([
                'message' => 'Only confirmed reservations can be checked in'
            ], 422);
        }

        $reservation->update([
            'status' => 'checked_in',
            'checked_in_at' => now()
        ]);

        // Update room status to occupied
        $reservation->room->update(['status' => 'occupied']);

        return response()->json([
            'message' => 'Guest checked in successfully',
            'reservation' => $reservation
        ]);
    }

    public function checkOut(Reservation $reservation): JsonResponse
    {
        if ($reservation->status !== 'checked_in') {
            return response()->json([
                'message' => 'Only checked-in reservations can be checked out'
            ], 422);
        }

        $reservation->update([
            'status' => 'completed',
            'checked_out_at' => now()
        ]);

        // Update room status to available
        $reservation->room->update(['status' => 'available']);

        return response()->json([
            'message' => 'Guest checked out successfully',
            'reservation' => $reservation
        ]);
    }

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
                'message' => 'Reservation not found'
            ], 404);
        }

        return response()->json($reservation);
    }
}