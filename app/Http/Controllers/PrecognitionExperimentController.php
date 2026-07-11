<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExperimentNodeRequest;
use Illuminate\Http\JsonResponse;

class PrecognitionExperimentController extends Controller
{
    public function store(StoreExperimentNodeRequest $request): JsonResponse
    {
        return response()->json([
            'message' => 'Node configuration accepted.',
        ]);
    }
}
