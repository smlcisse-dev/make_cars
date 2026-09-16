<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Controller;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AppointmentController extends Controller
{
    /**
     * Supervision admin en lecture seule (CLAUDE.md §5 règle 8) — aucune
     * action de confirmation/refus/reprogrammation côté admin, réservées au
     * garagiste concerné.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $appointments = Appointment::query()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('garage_id'), fn ($query) => $query->where('garage_id', $request->integer('garage_id')))
            ->with(['garage', 'repairService', 'user'])
            ->latest('requested_at')
            ->paginate();

        return AppointmentResource::collection($appointments);
    }

    public function show(Appointment $appointment): AppointmentResource
    {
        return new AppointmentResource($appointment->load(['garage', 'repairService', 'user']));
    }
}
