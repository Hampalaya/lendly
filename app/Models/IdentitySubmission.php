<?php

namespace App\Models;

use App\Enums\NotificationType;
use App\Notifications\TalaNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class IdentitySubmission extends Model
{
    public const NATIONAL_ID = 'national_id';

    public const OTHER_PHOTO_ID = 'other_photo_id';

    public const NATIONAL_ID_QR = 'national_id_qr';

    public const DOCUMENT_TYPES = [
        self::NATIONAL_ID => 'Philippine National ID',
        self::OTHER_PHOTO_ID => 'School, work, or other photo ID',
    ];

    public const ISSUER_CHECKS = [
        'issuer_lookup' => 'Official issuer lookup or QR checker',
        'issuer_confirmation' => 'Direct confirmation from the issuer',
    ];

    protected $guarded = ['id'];

    protected $hidden = ['front_path', 'back_path', 'selfie_path', 'profile_hash', 'extracted_name'];

    protected function casts(): array
    {
        return ['face_similarity' => 'float', 'extracted_name' => 'encrypted', 'consented_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }

    public static function profileHash(User $member): string
    {
        return hash_hmac('sha256', json_encode([$member->id, $member->name, $member->email]), config('app.key'));
    }

    public function purgeDocuments(): void
    {
        if ($this->getRawOriginal('extracted_name') !== null) {
            $this->update(['extracted_name' => null]);
        }

        $paths = array_filter([$this->front_path, $this->back_path, $this->selfie_path]);
        DB::afterCommit(function () use ($paths) {
            if (Storage::disk('local')->delete($paths)) {
                static::whereKey($this->id)->update(['front_path' => null, 'back_path' => null, 'selfie_path' => null]);
            }
        });
    }

    public static function cancelPending(int $userId): void
    {
        static::where('user_id', $userId)->where('status', 'pending')->get()->each(function ($submission) {
            $submission->update(['status' => 'cancelled']);
            $submission->notifyProgress();
            $submission->purgeDocuments();
        });
    }

    public function notifyProgress(): void
    {
        if (! $this->user || $this->user->trashed()) {
            return;
        }
        $message = match ($this->status) {
            'approved' => 'Your identity review was approved. You can rent and publish listings.',
            'rejected' => 'Your identity review needs a new submission.'.($this->review_notes ? ' '.$this->review_notes : ''),
            'cancelled' => 'Your pending review expired or your account details changed. Review your details and submit again.',
            default => 'Your documents were received and are awaiting administrator review.',
        };
        $this->user->notify(new TalaNotification(NotificationType::VerificationUpdate->value,
            'Identity review '.($this->status === 'pending' ? 'pending' : $this->status), $message, route('identity.verify')));
    }
}
