<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Payment::with(['reservation.user', 'reservation.hotel']);
        
        if ($request->has('reservation_id')) {
            $query->where('reservation_id', $request->reservation_id);
        }
        
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->has('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }
        
        if ($request->has('gateway')) {
            $query->where('gateway', $request->gateway);
        }
        
        if ($request->has('from_date')) {
            $query->where('created_at', '>=', $request->from_date);
        }
        
        if ($request->has('to_date')) {
            $query->where('created_at', '<=', $request->to_date);
        }
        
        $payments = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));
        
        return response()->json($payments);
    }

    public function store(Request $request): JsonResponse
{
    $validated = $request->validate([
        'reservation_id' => 'required|exists:reservations,id',
        'amount' => 'required|numeric|min:0.01',
        'currency' => 'required|string|size:3',
        'payment_method' => 'required|in:credit_card,debit_card,paypal,bank_transfer,cash',
        'gateway' => 'nullable|string',
        'gateway_response' => 'nullable|array'
    ]);

    $reservation = Reservation::find($validated['reservation_id']);
    
    // Check if payment amount doesn't exceed pending amount
    if ($validated['amount'] > $reservation->pending_amount) {
        return response()->json([
            'status' => 'error',
            'message' => 'Payment amount cannot exceed pending amount'
        ], 422);
    }

    $payment = Payment::create(array_merge($validated, [
        'payment_id' => 'PAY-' . strtoupper(uniqid()),
        'status' => 'pending',
        'gateway' => $validated['gateway'] ?? 'stripe'
    ]));

    $payment->load(['reservation.user', 'reservation.hotel']);

    // Return consistent response format
    return response()->json([
        'status' => 'success',
        'data' => $payment,
        'message' => 'Payment created successfully'
    ], 201);
}

    public function show(Payment $payment): JsonResponse
    {
        $payment->load(['reservation.user', 'reservation.hotel', 'reservation.room']);
        return response()->json($payment);
    }

    public function update(Request $request, Payment $payment): JsonResponse
    {
        // Only allow updates for pending payments
        if ($payment->status !== 'pending') {
            return response()->json([
                'message' => 'Cannot update payment with current status'
            ], 422);
        }

        $validated = $request->validate([
            'amount' => 'sometimes|numeric|min:0.01',
            'payment_method' => 'sometimes|in:credit_card,debit_card,paypal,bank_transfer,cash',
            'gateway' => 'nullable|string',
            'gateway_response' => 'nullable|array'
        ]);

        // If amount is being updated, check against reservation pending amount
        if (isset($validated['amount'])) {
            $reservation = $payment->reservation;
            $otherPaymentsAmount = $reservation->payments()
                ->where('id', '!=', $payment->id)
                ->where('status', 'completed')
                ->sum('amount');
            
            $newPendingAmount = $reservation->total_amount - $otherPaymentsAmount - $validated['amount'];
            
            if ($newPendingAmount < 0) {
                return response()->json([
                    'message' => 'Payment amount cannot exceed pending amount'
                ], 422);
            }
        }

        $payment->update($validated);
        $payment->load(['reservation.user', 'reservation.hotel']);

        return response()->json($payment);
    }

    public function destroy(Payment $payment): JsonResponse
    {
        // Only allow deletion of pending payments
        if ($payment->status !== 'pending') {
            return response()->json([
                'message' => 'Cannot delete payment with current status'
            ], 422);
        }

        $payment->delete();
        return response()->json(['message' => 'Payment deleted successfully']);
    }

    public function complete(Request $request, Payment $payment): JsonResponse
    {
    

        $validated = $request->validate([
            'gateway_response' => 'nullable|array'
        ]);

        $payment->update([
            'status' => 'completed',
            'processed_at' => now(),
            'gateway_response' => $validated['gateway_response'] ?? $payment->gateway_response
        ]);

        // Update reservation payment status
        $reservation = $payment->reservation;
        $totalPaid = $reservation->payments()->where('status', 'completed')->sum('amount');
        
        $reservation->update([
            'paid_amount' => $totalPaid,
            'pending_amount' => $reservation->total_amount - $totalPaid,
            // 'payment_status' => $totalPaid >= $reservation->total_amount ? 'completed' : 'partial'
        ]);

        return response()->json([
            'message' => 'Payment completed successfully',
            'payment' => $payment,
            'reservation' => $reservation
        ]);
    }

    public function fail(Request $request, Payment $payment): JsonResponse
    {
        if ($payment->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending payments can be marked as failed'
            ], 422);
        }

        $validated = $request->validate([
            'gateway_response' => 'nullable|array'
        ]);

        $payment->update([
            'status' => 'failed',
            'processed_at' => now(),
            'gateway_response' => $validated['gateway_response'] ?? $payment->gateway_response
        ]);

        return response()->json([
            'message' => 'Payment marked as failed',
            'payment' => $payment
        ]);
    }

    public function refund(Request $request, Payment $payment): JsonResponse
    {
        if ($payment->status !== 'completed') {
            return response()->json([
                'message' => 'Only completed payments can be refunded'
            ], 422);
        }

        $validated = $request->validate([
            'refund_amount' => 'nullable|numeric|min:0.01|max:' . $payment->amount,
            'reason' => 'nullable|string',
            'gateway_response' => 'nullable|array'
        ]);

        $refundAmount = $validated['refund_amount'] ?? $payment->amount;

        // Create refund payment record
        $refund = Payment::create([
            'reservation_id' => $payment->reservation_id,
            'payment_id' => 'REF-' . strtoupper(uniqid()),
            'amount' => -$refundAmount, // Negative amount for refund
            'currency' => $payment->currency,
            'payment_method' => $payment->payment_method,
            'status' => 'completed',
            'gateway' => $payment->gateway,
            'gateway_response' => $validated['gateway_response'] ?? null,
            'processed_at' => now()
        ]);

        // Update reservation payment amounts
        $reservation = $payment->reservation;
        $totalPaid = $reservation->payments()->where('status', 'completed')->sum('amount');
        
        $reservation->update([
            'paid_amount' => $totalPaid,
            'pending_amount' => $reservation->total_amount - $totalPaid,
            'payment_status' => $totalPaid >= $reservation->total_amount ? 'completed' : 
                              ($totalPaid > 0 ? 'partial' : 'pending')
        ]);

        return response()->json([
            'message' => 'Payment refunded successfully',
            'refund' => $refund,
            'original_payment' => $payment,
            'reservation' => $reservation
        ]);
    }

    public function getByReservation(Reservation $reservation): JsonResponse
    {
        $payments = $reservation->payments()
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'reservation' => $reservation,
            'payments' => $payments,
            'payment_summary' => [
                'total_amount' => $reservation->total_amount,
                'paid_amount' => $reservation->paid_amount,
                'pending_amount' => $reservation->pending_amount,
                'payment_status' => $reservation->payment_status
            ]
        ]);
    }
}