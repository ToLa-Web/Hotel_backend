<?php

namespace App\Http\Controllers;

use App\Services\CloudinaryService;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    protected $cloudinaryService;

    public function __construct(CloudinaryService $cloudinaryService)
    {
        $this->cloudinaryService = $cloudinaryService;
    }

    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        $file = $request->file('image');
        $result = $this->cloudinaryService->uploadImage($file->getRealPath());

        if (!$result['success']) {
            return response()->json([
                'message' => 'Failed to upload image',
                'error' => $result['message']
            ], 500);
        }

        return response()->json([
            'message' => 'Image uploaded successfully',
            'url' => $result['url'],
            'public_id' => $result['public_id']
        ]);
    }

    public function uploadVideo(Request $request)
    {
        $request->validate([
            'video' => 'required|mimes:mp4,mov,avi|max:10240'
        ]);

        $file = $request->file('video');
        $result = $this->cloudinaryService->uploadVideo($file->getRealPath());

        if (!$result['success']) {
            return response()->json([
                'message' => 'Failed to upload video',
                'error' => $result['message']
            ], 500);
        }

        return response()->json([
            'message' => 'Video uploaded successfully',
            'url' => $result['url'],
            'public_id' => $result['public_id']
        ]);
    }
} 