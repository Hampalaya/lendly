<?php

// Test-only "time machine" for the time-based requirements (FR-19, FR-20, FR-74, FR-79):
// moves a rental's dates in the SQLite test DB so the scheduled jobs see it as due, overdue
// or expired, instead of waiting real days. Never runs against MySQL.
// Usage: php tests/e2e/fixtures/tweak.php <rentalId> start_date=-5 end_date=1 payment_due_at=-1h
//   dates are day offsets from today (Asia/Manila); payment_due_at takes an hour offset like -1h.

use App\Models\Rental;
use Illuminate\Support\Carbon;

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (config('database.default') !== 'sqlite') {
    fwrite(STDERR, "Refusing to run: DB_CONNECTION is not sqlite.\n");
    exit(1);
}

$rental = Rental::findOrFail((int) ($argv[1] ?? 0));
$changes = [];
foreach (array_slice($argv, 2) as $arg) {
    [$field, $value] = explode('=', $arg, 2);
    $changes[$field] = str_ends_with($value, 'h')
        ? Carbon::now()->addHours((int) rtrim($value, 'h'))
        : Carbon::today('Asia/Manila')->addDays((int) $value)->toDateString();
}
// Bookings also store exact times (Philippine day boundaries, saved in UTC); keep them in sync.
if (isset($changes['start_date'])) {
    $changes['starts_at'] = Carbon::parse($changes['start_date'], 'Asia/Manila')->startOfDay()->utc();
}
if (isset($changes['end_date'])) {
    $changes['ends_at'] = Carbon::parse($changes['end_date'], 'Asia/Manila')->setTime(23, 59)->utc();
}
$rental->forceFill($changes)->save();
// Keep the request's period consistent with the booking.
$rental->rentalRequest?->forceFill(array_intersect_key($changes, array_flip(['start_date', 'end_date', 'starts_at', 'ends_at'])))->save();

echo json_encode(['rental' => $rental->id] + array_map('strval', $changes)).PHP_EOL;
