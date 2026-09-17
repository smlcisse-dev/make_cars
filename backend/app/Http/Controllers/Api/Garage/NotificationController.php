<?php

namespace App\Http\Controllers\Api\Garage;

use App\Http\Controllers\Api\Controller;
use App\Http\Resources\PushNotificationResource;
use App\Models\PushNotification;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    public function __construct(private readonly PushNotificationService $notificationService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $notifications = $request->user()->pushNotifications()->latest()->paginate();

        return PushNotificationResource::collection($notifications);
    }

    public function markRead(Request $request, PushNotification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 404);

        $notification = $this->notificationService->markRead($notification);

        return $this->success(new PushNotificationResource($notification), 'Notification marquée comme lue.');
    }
}
