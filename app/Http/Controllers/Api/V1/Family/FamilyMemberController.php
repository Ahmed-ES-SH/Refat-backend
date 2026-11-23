<?php

namespace App\Http\Controllers\Api\V1\Family;

use App\Http\Controllers\Controller;
use App\Http\Requests\Family\StoreFamilyMemberRequest;
use App\Http\Requests\Family\UpdateFamilyMemberRequest;
use App\Http\Resources\FamilyMemberResource;
use App\Models\FamilyMember;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FamilyMemberController extends Controller
{
    private const MAX_PER_PAGE = 50;

    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 15), 1), self::MAX_PER_PAGE);

        $members = $request->user()
            ->familyMembers()
            ->orderBy('id')
            ->paginate($perPage);

        return ApiResponse::paginated($members, FamilyMemberResource::class);
    }

    public function store(StoreFamilyMemberRequest $request): JsonResponse
    {
        $member = $request->user()->familyMembers()->create($request->validated());

        return FamilyMemberResource::make($member)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, int $familyMember): FamilyMemberResource
    {
        return FamilyMemberResource::make($this->findOwned($request, $familyMember));
    }

    public function update(UpdateFamilyMemberRequest $request, int $familyMember): FamilyMemberResource
    {
        $member = $this->findOwned($request, $familyMember);
        $member->update($request->validated());

        return FamilyMemberResource::make($member);
    }

    public function destroy(Request $request, int $familyMember): Response
    {
        $this->findOwned($request, $familyMember)->delete();

        return response()->noContent();
    }

    /**
     * Resolve through the owner relation so a foreign id is a 404, never a 403.
     */
    private function findOwned(Request $request, int $familyMember): FamilyMember
    {
        return $request->user()->familyMembers()->findOrFail($familyMember);
    }
}
