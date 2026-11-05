<?php

namespace App\Models;

use App\Enums\DamageReportStatus;
use App\Enums\DisputeStatus;
use App\Enums\ListingStatus;
use App\Enums\RentalRequestStatus;
use App\Enums\RentalStatus;
use App\Enums\ReviewType;
use App\Enums\SecurityDepositStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected static function booted(): void
    {
        static::updated(function (User $user) {
            if ($user->wasChanged(['name', 'email']) || ($user->wasChanged('status') && $user->isSuspended())) {
                $clearedIdentity = [
                    'identity_verified_at' => null,
                    'identity_reference' => null,
                    'identity_verification_environment' => null,
                ];
                if ($user->wasChanged('email')) {
                    $clearedIdentity['google_id'] = null;
                }
                // Clear persisted verification even when this profile was loaded before another request verified it.
                $user->newQuery()->whereKey($user->id)->update($clearedIdentity);
                $user->forceFill($clearedIdentity);
                IdentityVerificationAttempt::where('user_id', $user->id)
                    ->whereIn('status', ['pending', 'processing'])->update(['status' => 'cancelled']);
                IdentitySubmission::cancelPending($user->id);
            }
        });

        static::deleting(function (User $user) {
            if ($user->isForceDeleting()) {
                IdentitySubmission::cancelPending($user->id);

                return;
            }

            $email = $user->email;
            $avatar = $user->avatar_path;
            $user->cartItems()->delete();
            $user->forceFill([
                'name' => 'Deleted account',
                'email' => 'deleted-'.Str::uuid().'@example.invalid',
                'password' => Str::random(64),
                'phone' => null,
                'address' => null,
                'avatar_path' => null,
                'remember_token' => null,
                'email_verified_at' => null,
                'identity_verified_at' => null,
                'identity_reference' => null,
                'identity_verification_environment' => null,
                'status' => UserStatus::Suspended,
                'google_id' => null,
                'suspended_at' => now(),
                'suspension_reason' => null,
            ])->save();

            foreach (OwnerPaymentSetting::where('user_id', $user->id)->get() as $wallet) {
                $wallet->delete();
                if ($wallet->qr_path) {
                    OwnerPaymentSetting::deleteUnreferencedQr($wallet->qr_path);
                }
            }

            // Keep shared transaction evidence while removing listings from the marketplace.
            $user->listings()->update([
                'status' => ListingStatus::Inactive,
                'is_available' => false,
                'deleted_at' => now(),
            ]);
            DB::table(config('auth.passwords.users.table'))->where('email', $email)->delete();
            if (config('session.driver') === 'database') {
                DB::connection(config('session.connection'))->table(config('session.table'))
                    ->where('user_id', $user->id)->delete();
            }

            if ($avatar) {
                DB::afterCommit(fn () => Storage::disk('public')->delete($avatar));
            }
        });
    }

    public function delete()
    {
        return DB::transaction(fn () => parent::delete());
    }

    /**
     * The model's default attribute values.
     *
     * Set explicitly (in addition to the DB column defaults) because Eloquent
     * does not re-hydrate a freshly created model from the database, so any
     * attribute omitted from create() would otherwise be missing in-memory.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => 'member',
        'status' => 'active',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'address',
        'avatar_path',
        'status',
        'suspended_at',
        'suspension_reason',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'google_id',
        'password',
        'remember_token',
        'identity_verified_at',
        'identity_reference',
        'identity_verification_environment',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'identity_verified_at' => 'datetime',
            'identity_reference' => 'encrypted',
            'suspended_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
        ];
    }

    /**
     * Owner and Renter are no longer separate account types — every
     * non-admin member can both list items and rent items on the same
     * account, so both methods simply mean "is a regular member" (as
     * opposed to Admin). Kept as two named methods (rather than collapsing
     * every call site to `! isAdmin()`) since `isRenter()`/`isOwner()` still
     * read clearly at each call site about which capability is being used.
     */
    public function isRenter(): bool
    {
        return ! $this->isAdmin();
    }

    public function isOwner(): bool
    {
        return ! $this->isAdmin();
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isSuspended(): bool
    {
        return $this->status === UserStatus::Suspended;
    }

    public function hasVerifiedIdentity(): bool
    {
        return $this->identity_verified_at !== null && in_array($this->identity_verification_environment, ['production', 'manual'], true)
            && $this->hasVerifiedEmail() && ! $this->isAdmin() && ! $this->isSuspended() && ! $this->trashed();
    }

    public function hasTestedIdentity(): bool
    {
        return $this->identity_verified_at !== null && $this->identity_verification_environment === 'sandbox'
            && $this->hasVerifiedEmail() && ! $this->isAdmin() && ! $this->isSuspended() && ! $this->trashed();
    }

    public function closeAccount(): void
    {
        DB::transaction(function () {
            $this->newQuery()->whereKey($this->id)->lockForUpdate()->firstOrFail();
            $this->refresh();

            $hasOutstandingRental = Rental::query()
                ->where(fn ($query) => $query->where('owner_id', $this->id)->orWhere('renter_id', $this->id))
                ->where(fn ($query) => $query
                    ->whereNotIn('status', [RentalStatus::Completed, RentalStatus::Cancelled])
                    ->orWhereHas('disputes', fn ($dispute) => $dispute->where('status', '!=', DisputeStatus::Resolved))
                    ->orWhereHas('securityDeposit', fn ($deposit) => $deposit->whereNotIn('status', [SecurityDepositStatus::Released, SecurityDepositStatus::Refunded, SecurityDepositStatus::Deducted])
                        ->orWhere(fn ($pendingReceipt) => $pendingReceipt->whereNotNull('released_at')->whereNull('received_at')->whereColumn('amount', '>', 'deducted_amount')))
                    ->orWhereHas('damageReport', fn ($damage) => $damage->whereNotIn('status', [DamageReportStatus::Accepted, DamageReportStatus::Rejected])))
                ->exists();
            $hasOutstandingRequest = RentalRequest::query()
                ->where(fn ($query) => $query->where('renter_id', $this->id)
                    ->orWhereHas('listing', fn ($listing) => $listing->withTrashed()->where('owner_id', $this->id)))
                ->where(fn ($query) => $query->where('status', RentalRequestStatus::Requested)
                    ->orWhere(fn ($approved) => $approved->whereIn('status', [RentalRequestStatus::AwaitingAgreement, RentalRequestStatus::Approved])->whereDoesntHave('rental')))
                ->exists();

            if ($hasOutstandingRental || $hasOutstandingRequest) {
                throw ValidationException::withMessages([
                    'account' => 'Settle your rental requests, rentals, deposits, damage claims, and disputes before deleting your account.',
                ]);
            }

            $this->delete();
        });
    }

    public function dashboardRouteName(): string
    {
        if (! $this->isAdmin() && in_array(session('active_interface'), ['renter', 'owner'], true)) {
            return session('active_interface').'.dashboard';
        }

        return match ($this->role) {
            UserRole::Member => 'dashboard',
            UserRole::Admin => 'admin.dashboard',
        };
    }

    public function activeInterface(): string
    {
        return $this->isAdmin() ? 'admin' : session('active_interface', 'renter');
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? asset('storage/'.$this->avatar_path) : null;
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class, 'owner_id');
    }

    public function rentalRequests(): HasMany
    {
        return $this->hasMany(RentalRequest::class, 'renter_id');
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function rentalsAsRenter(): HasMany
    {
        return $this->hasMany(Rental::class, 'renter_id');
    }

    public function rentalsAsOwner(): HasMany
    {
        return $this->hasMany(Rental::class, 'owner_id');
    }

    /** Received person reviews from completed transactions, in either member role. */
    public function receivedReviews(): Builder
    {
        return Review::query()
            ->whereHas('rental', fn ($query) => $query->where('status', RentalStatus::Completed)
                ->whereColumn('owner_id', '!=', 'renter_id'))
            ->where(function ($query) {
                $query->where(fn ($query) => $query->where('type', ReviewType::RenterToOwner)
                    ->whereHas('rental', fn ($query) => $query->where('owner_id', $this->id)))
                    ->orWhere(fn ($query) => $query->where('type', ReviewType::OwnerToRenter)
                        ->whereHas('rental', fn ($query) => $query->where('renter_id', $this->id)));
            });
    }

    public function averageRatingAsOwner(): ?float
    {
        return $this->receivedReviews()
            ->where('type', ReviewType::RenterToOwner)
            ->avg('rating');
    }

    public function averageRatingAsRenter(): ?float
    {
        return $this->receivedReviews()
            ->where('type', ReviewType::OwnerToRenter)
            ->avg('rating');
    }

    public function messagesSent(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function messagesReceived(): HasMany
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }

    public function unreadMessagesCount(): int
    {
        return $this->messagesReceived()->whereNull('read_at')
            ->when(session('active_interface') === 'renter', fn ($query) => $query
                ->where(fn ($query) => $query
                    ->whereHas('rentalRequest', fn ($request) => $request->where('renter_id', $this->id))
                    ->orWhereHas('listingConversation', fn ($conversation) => $conversation->where('renter_id', $this->id))))
            ->when(session('active_interface') === 'owner', fn ($query) => $query
                ->where(fn ($query) => $query
                    ->whereHas('rentalRequest.listing', fn ($listing) => $listing->where('owner_id', $this->id))
                    ->orWhereHas('listingConversation', fn ($conversation) => $conversation->where('owner_id', $this->id))))
            ->count();
    }

    public function notificationsForActiveInterface(): MorphMany
    {
        $notifications = $this->notifications();
        $interface = session('active_interface');

        if ($this->isAdmin() || ! in_array($interface, ['renter', 'owner'], true)) {
            return $notifications;
        }

        $otherInterface = $interface === 'owner' ? 'renter' : 'owner';
        $chatUrls = RentalRequest::query()
            ->when($interface === 'renter', fn ($query) => $query->where('renter_id', $this->id))
            ->when($interface === 'owner', fn ($query) => $query->whereHas('listing', fn ($listing) => $listing->where('owner_id', $this->id)))
            ->pluck('id')
            ->map(fn ($id) => route('rental-requests.chat', $id));

        $listingChatUrls = ListingConversation::query()
            ->when($interface === 'renter', fn ($query) => $query->where('renter_id', $this->id))
            ->when($interface === 'owner', fn ($query) => $query->where('owner_id', $this->id))
            ->pluck('id')
            ->flatMap(fn ($id) => [route('listing-conversations.show', $id), route('messages.listing', $id)]);

        return $notifications->where(function ($query) use ($otherInterface, $chatUrls, $listingChatUrls) {
            $query->whereNull('data->url')->orWhere(function ($query) use ($otherInterface, $chatUrls, $listingChatUrls) {
                $query->where('data->url', 'not like', '%/'.$otherInterface.'/%')
                    ->where(function ($query) use ($chatUrls) {
                        $query->where('data->url', 'not like', '%/rental-requests/%/chat')
                            ->orWhereIn('data->url', $chatUrls);
                    })
                    ->where(function ($query) use ($listingChatUrls) {
                        $query->where(fn ($query) => $query
                            ->where('data->url', 'not like', '%/listing-conversations/%')
                            ->where('data->url', 'not like', '%/messages/listings/%'))
                            ->orWhereIn('data->url', $listingChatUrls);
                    });
            });
        });
    }
}
