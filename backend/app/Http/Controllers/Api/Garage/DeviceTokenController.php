<?php

namespace App\Http\Controllers\Api\Garage;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\DeviceToken\StoreDeviceTokenRequest;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;

class DeviceTokenController extends Controller
{
    public function __construct(private readonly PushNotificationService $notificationService) {}

    /**
     * Enregistre ou met à jour le jeton FCM de l'appareil courant, à
     * appeler à chaque connexion (CLAUDE.md §5, ajout v0.12).
     */
    public function store(StoreDeviceTokenRequest $request): JsonResponse
    {
        $this->notificationService->registerDeviceToken(
            $request->user(),
            $request->string('token')->toString(),
            $request->filled('platform') ? $request->string('platform')->toString() : null
        );

        return $this->success(message: 'Jeton enregistré.');
    }
}
