<?php

namespace App\Http\Controllers;

use App\Models\CarPhoto;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

// Serves a car photo from the database. A photo never changes (a new upload is a new row),
// so browsers may keep it for a year and come back with If-None-Match at most.
class PhotoController extends Controller
{
    public function __invoke(Request $request, int $photo): Response
    {
        $etag = '"photo-'.$photo.'"';
        if ($request->header('If-None-Match') === $etag) {
            return response('', 304, ['ETag' => $etag]);
        }
        $row = CarPhoto::withoutGlobalScope('without-data')->whereHas('car')->select('data', 'mime')->findOrFail($photo);
        abort_if($row->data === null, 404);

        return response($row->data, 200, [
            'Content-Type' => $row->mime ?: 'image/jpeg',
            'Content-Length' => strlen($row->data),
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'ETag' => $etag,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
