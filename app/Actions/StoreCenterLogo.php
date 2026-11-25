<?php

namespace App\Actions;

use App\Exceptions\ApiException;
use App\Models\Center;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Stores a center logo on the `public` disk.
 *
 * The stored extension comes from the sniffed MIME type, never from the client
 * supplied name. Writing the file is all this class does: the caller saves
 * `logo_path` first and only then removes the previous file, so a failed save
 * can never leave the center pointing at a file that no longer exists.
 */
class StoreCenterLogo
{
    /**
     * @return string The path of the stored logo, relative to the disk root.
     */
    public function handle(Center $center, UploadedFile $file): string
    {
        $path = Storage::disk('public')->putFileAs(
            "centers/{$center->id}",
            $file,
            'logo_'.bin2hex(random_bytes(8)).'.'.$this->extensionFor($file),
        );

        if ($path === false) {
            throw new ApiException('LOGO_STORAGE_FAILED', __('center.logo_storage_failed'), 500);
        }

        return $path;
    }

    /**
     * Remove a stored logo file; a null or empty path is a no-op.
     */
    public function remove(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        Storage::disk('public')->delete($path);
    }

    /**
     * The extension is derived from the content of the upload.
     */
    private function extensionFor(UploadedFile $file): string
    {
        return match ($file->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new ApiException(
                'INVALID_LOGO_TYPE',
                __('center.invalid_logo_type'),
                422,
                ['logo' => [__('center.invalid_logo_type')]],
            ),
        };
    }
}
