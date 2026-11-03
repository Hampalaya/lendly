<?php

// Test-only fixtures for the module-level Selenium specs. Run by tests/e2e/run.sh against the
// throwaway SQLite DB (never MySQL). Each scenario is created through the app's own services
// (agreement, payments, lifecycle) so the states are real, then the specs drive the UI from there.
// Writes the created ids to tests/e2e/artifacts/fixtures.json for the specs to read.

use App\Enums\ListingStatus;
use App\Enums\RentalRequestStatus;
use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\OfflinePaymentSetting;
use App\Models\OwnerPaymentSetting;
use App\Models\Rental;
use App\Models\RentalRequest;
use App\Models\User;
use App\Services\RentalAgreement;
use App\Services\RentalLifecycle;
use App\Services\RentalPayments;
use App\Services\RentalRequestSubmission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (config('database.default') !== 'sqlite') {
    fwrite(STDERR, "Refusing to seed fixtures: DB_CONNECTION is not sqlite.\n");
    exit(1);
}

$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
Storage::disk('public')->put('listings/fx.png', $png);

// email_verified_at and the identity fields are not mass-assignable, so they are set with forceFill.
// Fixture members count as manually ID-verified (the same state an admin approval produces), since
// the face-match step needs a Python face model and real face photos that the test env doesn't have.
$user = fn (string $email, string $name, bool $idApproved = true) => tap(User::firstOrNew(['email' => $email]))->forceFill([
    'name' => $name, 'role' => 'member', 'status' => 'active',
    'email_verified_at' => now(), 'password' => Hash::make('password'),
    'identity_verified_at' => $idApproved ? now() : null,
    'identity_verification_environment' => $idApproved ? 'manual' : null,
    'identity_reference' => $idApproved ? 'QA-FIXTURE-'.strtoupper(substr(md5($email), 0, 8)) : null,
])->save() ? User::where('email', $email)->first() : null;

$admin = User::where('email', 'admin@tala.test')->firstOrFail();
$owner = $user('fx.owner@tala.test', 'Fixture Owner');
$renter = $user('fx.renter@tala.test', 'Fixture Renter');
$renter2 = $user('fx.renter2@tala.test', 'Fixture Renter Two');
$lockout = $user('fx.lockout@tala.test', 'Fixture Lockout');
$suspend = $user('fx.suspend@tala.test', 'Fixture Suspend');
// Email confirmed but no approved ID review yet (identity gating and admin rejection).
$newbie = $user('fx.newbie@tala.test', 'Fixture Newbie', false);
$profile = $user('fx.profile@tala.test', 'Fixture Profile');
$reset = $user('fx.reset@tala.test', 'Fixture Reset');
$lockout2 = $user('fx.lockout2@tala.test', 'Fixture Lockout Two');
// A member with no rentals, for account closure (FR-88).
$closer = $user('fx.closer@tala.test', 'Fixture Closer');

OfflinePaymentSetting::updateOrCreate(['id' => 1], [
    'method' => 'bank_transfer', 'enabled' => true, 'updated_by' => $admin->id,
    'instructions' => 'QA: Transfer the full amount to BDO 0000-1111-2222 (Tala QA) and upload the receipt.',
]);
// Owner wallet (FR-72): a GCash QR so new bookings get an owner-wallet payment destination.
Storage::disk('local')->put('owner-payment-qr/fx-owner-qr.png', $png);
OwnerPaymentSetting::updateOrCreate(['user_id' => $owner->id], [
    'provider' => 'gcash', 'account_name' => 'Fixture Owner', 'enabled' => true,
    'instructions' => 'QA: scan the GCash QR, pay the full amount, then upload the receipt.',
    'qr_path' => 'owner-payment-qr/fx-owner-qr.png',
]);
$categoryId = Category::whereNull('parent_id')->orderBy('id')->value('id');

$listing = function (string $name, float $price, float $deposit = 1000) use ($owner, $categoryId) {
    $listing = Listing::create([
        'owner_id' => $owner->id, 'category_id' => $categoryId, 'name' => $name,
        'brand' => 'QA', 'model' => 'Fixture', 'condition' => 'good',
        'description' => "Fixture item {$name} for module tests.",
        'price_per_day' => $price, 'security_deposit' => $deposit,
        'location' => 'Nasugbu, Batangas', 'latitude' => 14.0723, 'longitude' => 120.6328,
        'pickup_available' => true, 'delivery_available' => false,
        'rental_rules' => 'Return clean.', 'max_rental_duration_days' => 7, 'is_available' => true,
        'available_from' => today()->toDateString(), 'available_until' => today()->addDays(60)->toDateString(),
        'status' => ListingStatus::Published,
    ]);
    ListingImage::create(['listing_id' => $listing->id, 'path' => 'listings/fx.png', 'sort_order' => 0]);

    return $listing;
};

// Requests go through the app's own submission service, so the rental unit, quote and saved
// terms match what the request form produces.
$request = function (Listing $listing, User $renter, int $startIn, int $days, string $method = 'pickup') {
    return RentalRequestSubmission::submit($renter, $listing->id, [
        'start_date' => today('Asia/Manila')->addDays($startIn)->toDateString(),
        'end_date' => today('Asia/Manila')->addDays($startIn + $days - 1)->toDateString(),
        'fulfillment_method' => $method, 'rental_unit' => 'day',
    ], RentalRequestSubmission::termsFor($listing->fresh()), true);
};

// The same state the owner's "Send for agreement" action produces.
$approve = function (RentalRequest $request) {
    $request->update([
        'status' => RentalRequestStatus::AwaitingAgreement,
        'renter_terms_accepted_at' => null, 'owner_terms_accepted_at' => null,
        'agreement_terms' => RentalAgreement::termsFor($request->fresh('listing')),
    ]);

    return $request->fresh();
};

$book = function (RentalRequest $request) use ($approve, $owner): Rental {
    $request = $approve($request);
    RentalAgreement::accept($request, $request->renter);

    return RentalAgreement::accept($request->fresh(), $owner);
};

$proof = function () use ($png) {
    $path = tempnam(sys_get_temp_dir(), 'fxproof').'.png';
    file_put_contents($path, $png);

    return new UploadedFile($path, 'proof.png', 'image/png', null, true);
};

// Owner-wallet payment: the renter submits proof and the owner confirms receipt (FR-41, FR-73).
$pay = function (Rental $rental, string $reference) use ($proof, $owner) {
    $submission = RentalPayments::submit($rental, $rental->renter, $reference, $proof());
    RentalPayments::confirmOwnerReceipt($rental, $owner, $submission->id, true);

    return $rental->fresh();
};

// Browse / search module: two items with distinct names and prices.
$tent = $listing('FX Camping Tent', 300);
$drone = $listing('FX Camera Drone', 1500);

// Listings module: an item the owner edits, pauses and reactivates.
$ladder = $listing('FX Aluminum Ladder', 250);

// Requests module: decline, overlap auto-decline and renter cancellation, all on one item.
$projector = $listing('FX Projector', 400);
$reqDecline = $request($projector, $renter, 5, 2);
$reqOverlapA = $request($projector, $renter, 10, 3);
$reqOverlapB = $request($projector, $renter2, 11, 3);
$reqCancel = $request($projector, $renter2, 20, 2);

// Agreement module: approved, waiting for both parties to accept.
$speaker = $listing('FX Party Speaker', 350);
$reqAgree = $approve($request($speaker, $renter, 3, 2));

// Payments module: booking confirmed, no payment yet.
$sony = $listing('FX Mirrorless Camera', 500);
$rentalPay = $book($request($sony, $renter, 4, 2));

// Cancellation module: paid bookings starting tomorrow (fee) and in 10 days (free).
$bike = $listing('FX Mountain Bike', 600);
$rentalCancelFee = $pay($book($request($bike, $renter, 1, 2)), 'FXPAYBIKE');
$kayak = $listing('FX Kayak', 700);
$rentalCancelFree = $pay($book($request($kayak, $renter, 10, 2)), 'FXPAYKAYAK');

// Return / damage / dispute module: an active rental (paid and handed over).
$guitar = $listing('FX Acoustic Guitar', 450);
$rentalReturn = $pay($book($request($guitar, $renter, 1, 2)), 'FXPAYGUITAR');
RentalLifecycle::confirmPickup($rentalReturn, $owner);
RentalLifecycle::confirmPickup($rentalReturn->fresh(), $renter);

// Messaging and admin moderation modules.
$tripod = $listing('FX Camera Tripod', 150);
$suspicious = $listing('FX Suspicious Listing', 99);

// Pickup scheduling module: a paid booking with no exchange arrangement yet.
$drill = $listing('FX Power Drill', 200);
$rentalSchedule = $pay($book($request($drill, $renter, 5, 2)), 'FXPAYDRILL');

// Map distance tests (FR-50, FR-51): one listing far from the Nasugbu fixtures, in Manila (~70 km).
$manila = $listing('FX City Scooter', 350);
$manila->update(['location' => 'Ermita, Manila', 'latitude' => 14.5826, 'longitude' => 120.9787]);

// Rental tracking (FR-19, FR-20, FR-79): an active rental whose dates the tests move.
$lantern = $listing('FX Camping Lantern', 250);
$rentalTrack = $pay($book($request($lantern, $renter, 1, 3)), 'FXPAYLANTERN');
RentalLifecycle::confirmPickup($rentalTrack, $owner);
RentalLifecycle::confirmPickup($rentalTrack->fresh(), $renter);

// Unpaid reservation expiry (FR-74): confirmed booking, no payment.
$cooler = $listing('FX Ice Cooler', 180);
$rentalExpire = $book($request($cooler, $renter2, 6, 2));

// Packing and courier stages (FR-76): a paid delivery booking.
$deliveryItem = $listing('FX Delivery Speaker', 320);
$deliveryItem->update(['delivery_available' => true]);
$rentalDelivery = $pay($book($request($deliveryItem->fresh(), $renter, 8, 2, 'delivery')), 'FXPAYDELIVERY');

// Admin transaction actions and audit trail (FR-28, FR-89): a paid booking an admin can cancel.
$grill = $listing('FX Portable Grill', 400);
$rentalAdmin = $pay($book($request($grill, $renter, 12, 2)), 'FXPAYGRILL');

$ids = [
    'users' => collect(compact('owner', 'renter', 'renter2', 'lockout', 'suspend', 'newbie', 'profile', 'reset', 'lockout2', 'closer'))
        ->map(fn (User $u) => $u->only('id', 'email', 'name'))->all(),
    'listings' => collect(compact('tent', 'drone', 'ladder', 'projector', 'speaker', 'sony', 'bike', 'kayak', 'guitar', 'tripod', 'suspicious', 'drill', 'lantern', 'cooler', 'deliveryItem', 'grill'))
        ->map(fn ($l) => ['id' => $l->id, 'name' => $l->name])->all(),
    'requests' => collect(compact('reqDecline', 'reqOverlapA', 'reqOverlapB', 'reqCancel', 'reqAgree'))->map->id->all(),
    'rentals' => [
        'pay' => $rentalPay->id, 'cancelFee' => $rentalCancelFee->id,
        'cancelFree' => $rentalCancelFree->id, 'return' => $rentalReturn->id,
        'schedule' => $rentalSchedule->id, 'track' => $rentalTrack->id, 'expire' => $rentalExpire->id,
        'delivery' => $rentalDelivery->id, 'admin' => $rentalAdmin->id,
    ],
    'dates' => [
        // The drill's booked period, for the waitlist test (a valid but conflicting period).
        'drillStart' => today('Asia/Manila')->addDays(5)->toDateString(),
        'drillEnd' => today('Asia/Manila')->addDays(6)->toDateString(),
        // The grill's booked period: renter 2 waitlists it, then the admin cancels the booking (FR-71).
        'grillStart' => today('Asia/Manila')->addDays(12)->toDateString(),
        'grillEnd' => today('Asia/Manila')->addDays(13)->toDateString(),
        'drillExchange' => today('Asia/Manila')->addDays(5)->toDateString().'T10:00',
    ],
];

$out = $root.'/tests/e2e/artifacts/fixtures.json';
@mkdir(dirname($out), 0777, true);
file_put_contents($out, json_encode($ids, JSON_PRETTY_PRINT));
echo "Fixtures ready: ".count($ids['listings'])." listings, ".count($ids['requests'])." requests, ".count($ids['rentals'])." rentals\n";
