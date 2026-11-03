<?php

// Test-only: creates a pending identity submission for a member, standing in for the member's
// ID + selfie upload (FR-58) and the local face comparison (FR-59), which need real face photos
// and a Python face model that the test environment does not have. The admin then reviews and
// approves it through the real admin screen (FR-60). Runs only against the SQLite test DB.
// Usage: php tests/e2e/fixtures/identity-pending.php member@gmail.com

use App\Models\IdentitySubmission;
use App\Support\AuthEmail;
use Illuminate\Support\Facades\Storage;

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (config('database.default') !== 'sqlite') {
    fwrite(STDERR, "Refusing to run: DB_CONNECTION is not sqlite.\n");
    exit(1);
}

// Gmail addresses are stored normalized (dots and +tags removed), so look up the way the app does.
$member = AuthEmail::findUser($argv[1] ?? '') ?? throw new RuntimeException('Member not found: '.($argv[1] ?? ''));
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
$paths = [];
foreach (['front', 'selfie'] as $kind) {
    $paths["{$kind}_path"] = "identity-submissions/qa-{$member->id}-{$kind}.png";
    Storage::disk('local')->put($paths["{$kind}_path"], $png);
}

$submission = IdentitySubmission::create([
    'user_id' => $member->id, 'status' => 'pending', ...$paths,
    'profile_hash' => IdentitySubmission::profileHash($member),
    'face_similarity' => 0.82, 'consented_at' => now(),
]);

echo json_encode(['submission' => $submission->id, 'user' => $member->id]).PHP_EOL;
