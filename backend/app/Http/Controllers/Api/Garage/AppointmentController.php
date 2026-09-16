<?php

namespace App\Http\Controllers\Api\Garage;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Api\Controller;
use App\Http\Controllers\Concerns\ResolvesAuthenticatedGarage;
use App\Http\Requests\Garage\RejectAppointmentRequest;
use App\Http\Requests\Garage\RescheduleAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Services\AppointmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

class AppointmentController extends Controller
{
    use ResolvesAuthenticatedGarage;

    public function __construct(private readonly AppointmentService $appointmentService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $appointments = $this->authenticatedGarage($request)->appointments()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->with(['repairService', 'user'])
            ->latest('requested_at')
            ->paginate();

        return AppointmentResource::collection($appointments);
    }

    public function show(Request $request, Appointment $appointment): JsonResponse
    {
        $garage = $this->authenticatedGarage($request);
        abort_unless($appointment->garage_id === $garage->id, 404);

        return $this->success(new AppointmentResource($appointment->load(['repairService', 'user'])));
    }

    public function confirm(Request $request, Appointment $appointment): JsonResponse
    {
        $garage = $this->authenticatedGarage($request);
        abort_unless($appointment->garage_id === $garage->id, 404);
        abort_unless($appointment->status === AppointmentStatus::Pending, 403, 'Ce rendez-vous n\'est plus en attente.');

        $appointment = $this->appointmentService->confirm($appointment);

        return $this->success(new AppointmentResource($appointment), 'Rendez-vous confirmé.');
    }

    public function reject(RejectAppointmentRequest $request, Appointment $appointment): JsonResponse
    {
        $garage = $this->authenticatedGarage($request);
        abort_unless($appointment->garage_id === $garage->id, 404);
        abort_unless($appointment->status === AppointmentStatus::Pending, 403, 'Ce rendez-vous n\'est plus en attente.');

        $appointment = $this->appointmentService->reject($appointment, $request->string('reason')->toString() ?: null);

        return $this->success(new AppointmentResource($appointment), 'Rendez-vous refusé.');
    }

    public function reschedule(RescheduleAppointmentRequest $request, Appointment $appointment): JsonResponse
    {
        $garage = $this->authenticatedGarage($request);
        abort_unless($appointment->garage_id === $garage->id, 404);
        abort_unless($appointment->status === AppointmentStatus::Pending, 403, 'Ce rendez-vous n\'est plus en attente.');

        $appointment = $this->appointmentService->reschedule($appointment, Carbon::parse($request->string('proposed_at')->toString()));

        return $this->success(new AppointmentResource($appointment), 'Nouvelle date proposée au client.');
    }
}
