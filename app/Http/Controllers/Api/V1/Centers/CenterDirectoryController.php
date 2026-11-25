<?php

namespace App\Http\Controllers\Api\V1\Centers;

use App\Enums\CenterStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\DirectoryFilterRequest;
use App\Http\Resources\CenterDirectoryResource;
use App\Models\Center;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class CenterDirectoryController extends Controller
{
    private const PER_PAGE = 20;

    /**
     * List active centers with optional filtering.
     */
    public function index(DirectoryFilterRequest $request): JsonResponse
    {
        $query = Center::query()
            ->where('status', CenterStatus::Active->value)
            ->with(['doctors' => function ($query) {
                // `is_active` stays in the select: the resource filters the
                // collection on it, and a missing attribute filters every
                // doctor out.
                $query->where('is_active', true)->select('id', 'center_id', 'specialty', 'is_active');
            }]);

        if ($request->filled('city')) {
            $this->matchLiteral($query, ['city'], (string) $request->string('city'));
        }

        if ($request->filled('specialty')) {
            $query->whereHas('doctors', function ($query) use ($request) {
                $query->where('is_active', true)
                    ->where('specialty', (string) $request->string('specialty'));
            });
        }

        if ($request->filled('search')) {
            $this->matchLiteral($query, ['name', 'description'], (string) $request->string('search'));
        }

        $centers = $query->orderBy('name')->paginate($request->integer('per_page', self::PER_PAGE));

        return ApiResponse::paginated($centers, CenterDirectoryResource::class);
    }

    /**
     * Show a single center (public view).
     */
    public function show(Center $center): JsonResponse
    {
        $this->authorize('view', $center);

        $center->load(['doctors' => function ($query) {
            $query->where('is_active', true)->select('id', 'center_id', 'specialty', 'is_active');
        }]);

        return (new CenterDirectoryResource($center))->response();
    }

    /**
     * Match a literal substring on the given columns. The LIKE wildcards are
     * stripped so a search term can never widen the query, and a term that was
     * only wildcards matches nothing instead of everything.
     *
     * @param  array<int, string>  $columns
     */
    private function matchLiteral(Builder $query, array $columns, string $term): void
    {
        $term = str_replace(['%', '_'], '', $term);

        if ($term === '') {
            $query->whereIn($query->getModel()->getQualifiedKeyName(), []);

            return;
        }

        $query->where(function (Builder $query) use ($columns, $term): void {
            foreach ($columns as $column) {
                $query->orWhere($column, 'like', '%'.$term.'%');
            }
        });
    }
}
