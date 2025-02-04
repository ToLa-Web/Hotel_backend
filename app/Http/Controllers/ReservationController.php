<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    // Get all reservations
    public function index()
    {
        return Reservation::all();
    }

    // Create a new reservation
    public function store(Request $request)
    {
        $request->validate([
            'userName' => 'required|string',
            'startDate' => 'required|date',
            'endDate' => 'required|date',
            'amountPeople' => 'required|integer',
            'imageRoom' => 'nullable|string',
            'floor' => 'required|integer',
            'status' => 'required|in:paid,not paid',
            'email' => 'required|email',
        ]);

        return Reservation::create($request->all());
    }

    // Get a single reservation
    public function show($id)
    {
        return Reservation::findOrFail($id);
    }

    // Update a reservation
    public function update(Request $request, $id)
    {
        $reservation = Reservation::findOrFail($id);
        $reservation->update($request->all());
        return $reservation;
    }

    // Delete a reservation
    public function destroy($id)
    {
        return Reservation::destroy($id);
    }
}