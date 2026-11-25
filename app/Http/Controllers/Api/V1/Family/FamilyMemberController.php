<?php

namespace App\Http\Controllers\Api\V1\Family;

use App\Http\Controllers\Controller;
use App\Http\Requests\Family\FamilyMemberIndexRequest;
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
    private const PER_PAGE = 15;

    public function index(FamilyMemberIndexRequest $request): JsonResponse
    {
        $members = $request->user()
            ->familyMembers()
            ->orderBy('id')
            ->paginate($request->integer('per_page', self::PER_PAGE));

        return ApiResponse::paginated($members, FamilyMemberResource::class);
    }

    public function store(StoreFamilyMemberRequest $request): JsonResponse
    {
        $member = $request->user()->familyMembers()->create($request->validated());

        return FamilyMemberResource::make($member)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, int $familyMember): JsonResponse
    {
        return (new FamilyMemberResource($this->findOwned($request, $familyMember)))->response();
    }

    public function update(UpdateFamilyMemberRequest $request, int $familyMember): JsonResponse
    {
        $member = $this->findOwned($request, $familyMember);
        $member->update($request->validated());

        return (new FamilyMemberResource($member))->response();
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
