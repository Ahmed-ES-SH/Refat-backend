<?php

namespace App\Http\Controllers\Api\V1\Centers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAvailabilityRuleRequest;
use App\Http\Requests\UpdateAvailabilityRuleRequest;
use App\Http\Resources\AvailabilityRuleResource;
use App\Models\AvailabilityRule;
use App\Models\Doctor;
use Illuminate\Http\JsonResponse;

class AvailabilityRuleController extends Controller
{
    /**
     * List the weekly windows of a doctor.
     */
    public function index(Doctor $doctor): JsonResponse
    {
        $this->authorize('view', $doctor);

        $rules = $doctor->availabilityRules()
            ->orderBy('weekday')
            ->orderBy('start_time')
            ->get();

        return AvailabilityRuleResource::collection($rules)
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Add a weekly window to one of the center's doctors.
     */
    public function store(StoreAvailabilityRuleRequest $request, Doctor $doctor): JsonResponse
    {
        // Rules belong to a doctor, so seeing the doctor is the gate.
        $this->authorize('view', $doctor);

        $rule = $doctor->availabilityRules()->create($request->validated());

        return (new AvailabilityRuleResource($rule))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified rule.
     */
    public function show(AvailabilityRule $rule): JsonResponse
    {
        $this->authorize('view', $rule);

        return (new AvailabilityRuleResource($rule))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Update the specified rule.
     */
    public function update(UpdateAvailabilityRuleRequest $request, AvailabilityRule $rule): JsonResponse
    {
        $this->authorize('update', $rule);

        $rule->update($request->validated());

        return (new AvailabilityRuleResource($rule))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Remove the specified rule.
     */
    public function destroy(AvailabilityRule $rule): JsonResponse
    {
        $this->authorize('delete', $rule);

        $rule->delete();

        return response()->json([], 204);
    }
}
