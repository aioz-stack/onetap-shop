<?php

namespace App\Support;

use App\Models\Shipment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Proof-of-delivery photos live on the private "local" disk; every panel streams them through
 * its own authorized route instead of a public URL.
 */
class ProofPhoto
{
    public static function response(Shipment $shipment): StreamedResponse
    {
        $path = $shipment->proof()->value('photo_path');

        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, max-age=600', 'X-Content-Type-Options' => 'nosniff']);
    }
}
