<?php

namespace App\Http\Controllers\Api\V1\Centers;

use App\Actions\StoreCenterLogo;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCenterProfileRequest;
use App\Http\Resources\CenterResource;
use App\Models\Center;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Throwable;

class CenterProfileController extends Controller
{
    /**
     * Show the center profile (for center staff).
     */
    public function show(Center $center): JsonResponse
    {
        $this->authorize('view', $center);

        return (new CenterResource($center->load('staff')))->response();
    }

    /**
     * Update the center profile and optionally upload a logo.
     */
    public function update(UpdateCenterProfileRequest $request, Center $center): JsonResponse
    {
        $this->authorize('update', $center);

        $attributes = Arr::except($request->validated(), ['logo']);
        $logoStore = app(StoreCenterLogo::class);
        $previousLogoPath = $center->logo_path;
        $newLogoPath = null;

        if ($request->hasFile('logo')) {
            $newLogoPath = $logoStore->handle($center, $request->file('logo'));
            $attributes['logo_path'] = $newLogoPath;
        }

        try {
            $center->update($attributes);
        } catch (Throwable $exception) {
            // The row still points at the previous path, so the file that was
            // just written would only be an orphan on disk.
            $logoStore->remove($newLogoPath);

            throw $exception;
        }

        // The row now points at the new path; the previous file is safe to drop.
        if ($newLogoPath !== null) {
            $logoStore->remove($previousLogoPath);
        }

        return (new CenterResource($center->fresh()->load('staff')))->response();
    }

    /**
     * Remove the center logo from the public disk.
     *
     * Idempotent: deleting when no logo exists is a no-op that still returns
     * the resource, so retries are harmless.
     */
    public function deleteLogo(Center $center): JsonResponse
    {
        $this->authorize('update', $center);

        $previousLogoPath = $center->logo_path;

        if ($previousLogoPath !== null && $previousLogoPath !== '') {
            // Null the column first so a failed write cannot leave the row
            // pointing at a file that has already been removed.
            $center->update(['logo_path' => null]);

            app(StoreCenterLogo::class)->remove($previousLogoPath);
        }

        return (new CenterResource($center->load('staff')))->response();
    }
}
