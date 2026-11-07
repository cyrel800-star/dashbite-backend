<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\Rating;
use App\Models\RiderAttendance;
use Illuminate\Http\Request;

class AdminMonitoringController extends Controller
{
    public function ratings()
    {
        $ratings = Rating::with(['customer:id,name', 'rider:id,name', 'order:id'])
            ->orderByDesc('id')->paginate(20);
        return response()->json($ratings);
    }

    public function attendance()
    {
        $rows = RiderAttendance::with('rider:id,name')
            ->orderByDesc('date')->paginate(30);
        return response()->json($rows);
    }

    public function complaints()
    {
        $rows = Complaint::with('customer:id,name')
            ->orderByDesc('id')->paginate(20);
        return response()->json($rows);
    }

    public function resolveComplaint(Request $request, int $id)
    {
        $data = $request->validate([
            'resolution_notes' => ['required', 'string', 'max:2000'],
        ]);

        $complaint = Complaint::find($id);
        if (! $complaint) {
            return response()->json(['error' => 'complaint_not_found'], 404);
        }

        $complaint->update([
            'status'           => 'resolved',
            'resolution_notes' => $data['resolution_notes'],
            'resolved_at'      => now(),
        ]);

        return response()->json(['message' => 'Complaint resolved.', 'data' => $complaint]);
    }
}