<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\CenterStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CenterResource;
use App\Models\Center;
use Illuminate\Http\JsonResponse;

class CenterAdminController extends Controller
{
    /**
     * Approve a center (admin only).
     */
    public function approve(Center $center): JsonResponse
    {
        $this->authorize('approve', $center);

        $center->update([
            'status' => CenterStatus::Active->value,
            'approved_at' => now(),
        ]);

        return (new CenterResource($center->load('staff')))->response();
    }

    /**
     * Suspend a center (admin only).
     */
    public function suspend(Center $center): JsonResponse
    {
        $this->authorize('suspend', $center);

        $center->update([
            'status' => CenterStatus::Suspended->value,
        ]);

        return (new CenterResource($center->load('staff')))->response();
    }
}
