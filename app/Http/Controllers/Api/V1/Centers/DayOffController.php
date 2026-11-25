<?php

namespace App\Http\Controllers\Api\V1\Centers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDayOffRequest;
use App\Http\Requests\UpdateDayOffRequest;
use App\Http\Resources\DayOffResource;
use App\Models\DayOff;
use App\Models\Doctor;
use Illuminate\Http\JsonResponse;

class DayOffController extends Controller
{
    /**
     * List the full-day exceptions of a doctor.
     */
    public function index(Doctor $doctor): JsonResponse
    {
        $this->authorize('view', $doctor);

        $daysOff = $doctor->daysOff()->orderBy('date')->get();

        return DayOffResource::collection($daysOff)
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Add a full-day exception for one of the center's doctors.
     */
    public function store(StoreDayOffRequest $request, Doctor $doctor): JsonResponse
    {
        // Days off belong to a doctor, so seeing the doctor is the gate.
        $this->authorize('view', $doctor);

        $dayOff = $doctor->daysOff()->create($request->validated());

        return (new DayOffResource($dayOff))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified day off.
     */
    public function show(DayOff $dayOff): JsonResponse
    {
        $this->authorize('view', $dayOff);

        return (new DayOffResource($dayOff))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Update the specified day off.
     */
    public function update(UpdateDayOffRequest $request, DayOff $dayOff): JsonResponse
    {
        $this->authorize('update', $dayOff);

        $dayOff->update($request->validated());

        return (new DayOffResource($dayOff))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Remove the specified day off.
     */
    public function destroy(DayOff $dayOff): JsonResponse
    {
        $this->authorize('delete', $dayOff);

        $dayOff->delete();

        return response()->json([], 204);
    }
}
