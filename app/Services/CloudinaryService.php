<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;

class CloudinaryService
{
    protected $cloudinary;

    public function __construct()
    {
        \Log::info('Cloudinary config', [
            'cloud_name' => config('cloudinary.cloud_name'),
            'api_key'    => config('cloudinary.api_key'),
            'api_secret' => config('cloudinary.api_secret'),
            'secure'     => config('cloudinary.secure'),
        ]);

        \Cloudinary\Configuration\Configuration::instance([
            'cloud' => [
                'cloud_name' => config('cloudinary.cloud_name'),
                'api_key'    => config('cloudinary.api_key'),
                'api_secret' => config('cloudinary.api_secret'),
                'secure'     => config('cloudinary.secure'),
            ],
        ]);

        $this->cloudinary = new \Cloudinary\Cloudinary();
    }

    public function uploadImage($file, $options = [])
    {
        try {
            $result = $this->cloudinary->uploadApi()->upload($file, $options);
            return [
                'success' => true,
                'url' => $result['secure_url'],
                'public_id' => $result['public_id']
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function uploadVideo($file, $options = [])
    {
        try {
            $options['resource_type'] = 'video';
            $result = $this->cloudinary->uploadApi()->upload($file, $options);
            return [
                'success' => true,
                'url' => $result['secure_url'],
                'public_id' => $result['public_id']
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function delete($publicId, $resourceType = 'image')
    {
        try {
            $this->cloudinary->uploadApi()->destroy($publicId, ['resource_type' => $resourceType]);
            return [
                'success' => true,
                'message' => 'Resource deleted successfully'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
} 