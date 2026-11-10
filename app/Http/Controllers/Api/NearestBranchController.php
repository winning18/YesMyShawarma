<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Branches\NearestBranchFinder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NearestBranchController extends Controller
{
    public function __invoke(Request $request, NearestBranchFinder $finder): JsonResponse
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $result = $finder->find((float) $validated['lat'], (float) $validated['lng']);

        if (! $result) {
            return response()->json(['message' => __('No branch is available right now.')], 404);
        }

        return response()->json([
            'data' => [
                'branch' => ['id' => $result['branch']->id, 'name' => $result['branch']->name, 'slug' => $result['branch']->slug],
                'distance_km' => $result['distance_km'],
                'closer_closed' => $result['closer_closed']?->name,
            ],
        ]);
    }
}
