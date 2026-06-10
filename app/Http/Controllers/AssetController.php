<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadAssetRequest;
use App\Http\Resources\AssetResource;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class AssetController extends Controller
{
    /**
     * Store a newly created asset in storage.
     */
    public function store(UploadAssetRequest $request): AssetResource
    {
        /** @var UploadedFile $file */
        $file = $request->file('file');

        $path = $file->store('assets', 'public');

        $url = Storage::disk('public')->url($path);

        return new AssetResource([
            'path' => $path,
            'url' => $url,
        ]);
    }
}
