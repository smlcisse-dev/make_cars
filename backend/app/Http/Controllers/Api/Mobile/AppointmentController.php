<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Mobile\StoreAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Garage;
use App\Models\RepairService;
use App\Services\AppointmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AppointmentController extends Controller
{
    public function __construct(private readonly AppointmentService $appointmentService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $appointments = $request->user()->appointments()
            ->with(['garage', 'repairService'])
            ->latest('requested_at')
            ->paginate();

        return AppointmentResource::collection($appointments);
    }

    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        $garage = Garage::find($request->integer('garage_id'));
        abort_unless($garage && $garage->isPubliclyVisible(), 404, 'Garage introuvable.');

        $repairService = null;
        if ($request->filled('repair_service_id')) {
            $repairService = RepairService::find($request->integer('repair_service_id'));
            abort_unless(
                $repairService && $repairService->garage_id === $garage->id && $repairService->isPubliclyVisible(),
                404,
                'Service introuvable pour ce garage.'
            );
        }

        $appointment = $this->appointmentService->create($request->user(), $garage, $repairService, $request->validated());

        return $this->success(
            new AppointmentResource($appointment->load(['garage', 'repairService'])),
            'Demande de rendez-vous envoyée.',
            201
        );
    }

    public function show(Request $request, Appointment $appointment): JsonResponse
    {
        abort_unless($appointment->user_id === $request->user()->id, 404);

        return $this->success(new AppointmentResource($appointment->load(['garage', 'repairService'])));
    }

    /**
     * Annulation côté client — y compris pour refermer une contre-proposition
     * du garagiste (statut Rescheduled) qui ne convient pas (CLAUDE.md §5,
     * ajout v0.7) : pas de "refus" de contre-proposition, l'annulation suffit.
     */
    public function cancel(Request $request, Appointment $appointment): JsonResponse
    {
        abort_unless($appointment->user_id === $request->user()->id, 404);
        abort_unless(
            in_array($appointment->status, [AppointmentStatus::Pending, AppointmentStatus::Confirmed, AppointmentStatus::Rescheduled], strict: true),
            403,
            'Ce rendez-vous ne peut plus être annulé.'
        );

        $appointment = $this->appointmentService->cancel($appointment);

        return $this->success(new AppointmentResource($appointment), 'Rendez-vous annulé.');
    }

    public function acceptReschedule(Request $request, Appointment $appointment): JsonResponse
    {
        abort_unless($appointment->user_id === $request->user()->id, 404);
        abort_unless($appointment->status === AppointmentStatus::Rescheduled, 403, 'Aucune nouvelle date à accepter pour ce rendez-vous.');

        $appointment = $this->appointmentService->acceptReschedule($appointment);

        return $this->success(new AppointmentResource($appointment), 'Nouvelle date acceptée, rendez-vous confirmé.');
    }
}
