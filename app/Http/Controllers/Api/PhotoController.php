<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Photo;
use App\Services\PhotoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PhotoController extends Controller
{
    public function __construct(
        private readonly PhotoService $photoService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'photo' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png'],
            'related_type' => ['required', 'string', 'max:255'],
            'related_id' => ['required', 'uuid'],
        ]);

        try {
            $photo = $this->photoService->uploadPhoto(
                $request->file('photo'),
                $validated['related_type'],
                $validated['related_id'],
                $request->user(),
            );
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'photo' => [$exception->getMessage()],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $photo->id,
                'photo_path' => $photo->photo_path,
                'photo_url' => $this->photoService->getPhotoUrl($photo),
                'photo_timestamp' => $photo->photo_timestamp?->toDateTimeString(),
            ],
        ], 201);
    }

    public function show(Photo $photo): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $photo->id,
                'related_type' => $photo->related_type,
                'related_id' => $photo->related_id,
                'photo_path' => $photo->photo_path,
                'photo_url' => $this->photoService->getPhotoUrl($photo),
                'photo_timestamp' => $photo->photo_timestamp?->toDateTimeString(),
                'watermark_text' => $photo->watermark_text,
            ],
        ]);
    }
}
