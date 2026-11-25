<?php

namespace App\Http\Controllers\Api\V1\Centers;

use App\Actions\RegisterCenterWithStaff;
use App\Http\Controllers\Controller;
use App\Http\Requests\CenterRegistrationRequest;
use App\Http\Resources\CenterResource;
use Illuminate\Http\JsonResponse;

class CenterRegistrationController extends Controller
{
    /**
     * Register a new center with a staff account.
     */
    public function store(CenterRegistrationRequest $request): JsonResponse
    {
        $center = app(RegisterCenterWithStaff::class)->handle($request->validated());

        return (new CenterResource($center))
            ->response()
            ->setStatusCode(201);
    }
}
