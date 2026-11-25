<?php

namespace App\Http\Controllers\Api\V1\Centers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDoctorRequest;
use App\Http\Requests\UpdateDoctorRequest;
use App\Http\Resources\DoctorListResource;
use App\Http\Resources\DoctorResource;
use App\Models\Doctor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DoctorController extends Controller
{
    /**
     * List doctors for the authenticated user's center (center_staff) or all (admin).
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Doctor::class);

        $user = $request->user();

        $doctors = $user->role->isAdmin()
            ? Doctor::query()
            : Doctor::query()->where('center_id', $user->center_id);

        $doctors = $doctors->orderBy('name')->get();

        return DoctorListResource::collection($doctors)
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Store a new doctor for the authenticated user's center.
     */
    public function store(StoreDoctorRequest $request): JsonResponse
    {
        $this->authorize('create', Doctor::class);

        $data = $request->validated();
        $data['center_id'] = $request->user()->center_id;

        $doctor = Doctor::create($data);

        return (new DoctorResource($doctor))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified doctor.
     */
    public function show(Doctor $doctor): JsonResponse
    {
        $this->authorize('view', $doctor);

        return (new DoctorResource($doctor))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Update the specified doctor.
     */
    public function update(UpdateDoctorRequest $request, Doctor $doctor): JsonResponse
    {
        $this->authorize('update', $doctor);

        $doctor->update($request->validated());

        return (new DoctorResource($doctor))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Deactivate the specified doctor (soft delete via is_active = false).
     */
    public function destroy(Doctor $doctor): JsonResponse
    {
        $this->authorize('delete', $doctor);

        $doctor->update(['is_active' => false]);

        return response()->json([], 204);
    }
}
