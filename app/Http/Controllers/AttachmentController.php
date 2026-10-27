<?php

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Models\Attachment;
use App\Models\Owner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Attachment $attachment): StreamedResponse
    {
        $user = $request->user();
        abort_unless($attachment->attachable !== null, 404);

        if ($user === null) {
            abort(403);
        }

        if (! $user->hasRole(RoleName::Administrator) && ! $user->hasRole(RoleName::OfficeStaff)) {
            abort_unless($user->hasRole(RoleName::Owner), 403);
            abort_unless(
                $attachment->attachable instanceof Owner
                && $attachment->attachable->user_id === $user->id
                && $attachment->attachable->status === 'Current',
                404,
            );
        }

        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }
}
