<?php

namespace App\Http\Controllers;

use App\Models\OwnerPaymentSetting;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class OwnerPaymentQrController extends Controller
{
    public function current(?int $wallet = null)
    {
        $user = $this->viewer();
        abort_if($user->isAdmin(), 403);

        $settings = $wallet === null
            ? OwnerPaymentSetting::currentFor($user->id)
            : OwnerPaymentSetting::where('user_id', $user->id)->findOrFail($wallet);

        return $this->image($settings?->qr_path);
    }

    public function booking(Rental $rental)
    {
        $user = $this->viewer();
        $destination = $rental->payment_destination;
        abort_unless(($destination['source'] ?? null) === 'owner_wallet', 404);
        abort_unless($user->id === $rental->renter_id
            || $user->id === ($destination['payee_user_id'] ?? null)
            || Gate::forUser($user)->allows('viewAny', Rental::class), 403);

        return $this->image($destination['qr_path'] ?? null);
    }

    private function viewer(): User
    {
        $user = User::find(auth()->id());
        abort_unless($user && $user->hasVerifiedEmail() && ! $user->isSuspended(), 403);

        return $user;
    }

    private function image(?string $path)
    {
        $disk = Storage::disk('local');
        abort_unless($path && $disk->exists($path), 404);
        $mime = $disk->mimeType($path);
        abort_unless(in_array($mime, ['image/png', 'image/jpeg'], true), 404);

        return $disk->response($path, null, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
