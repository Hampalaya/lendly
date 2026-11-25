<?php

namespace Tests\Feature;

use App\Http\Controllers\IdentityDocumentController;
use App\Models\IdentitySubmission;
use App\Models\User;
use App\Services\FaceComparison;
use App\Services\ManualIdentityReview;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ManualIdentityReviewTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::set('local', Storage::fake('identity-review-'.Str::random(12)));
        $this->mock(FaceComparison::class)->shouldReceive('compare')->andReturn(0.63)->byDefault();
        Process::fake(fn () => Process::result(exitCode: 1));
        Route::get('/_test/identity-documents/{submission}/{kind}', IdentityDocumentController::class)
            ->middleware(SubstituteBindings::class);
    }

    private function submit(?User $member = null, string $documentType = 'national_id'): IdentitySubmission
    {
        return app(ManualIdentityReview::class)->submit($member ?? User::factory()->create(),
            UploadedFile::fake()->image('front.jpg', 640, 480),
            UploadedFile::fake()->image('back.png', 640, 480),
            UploadedFile::fake()->image('selfie.jpg', 640, 480), true, $documentType);
    }

    public function test_submission_is_private_pending_and_stores_the_server_face_score(): void
    {
        Process::fake(fn () => Process::result(output: "Student ID\nName: Maria Elena Santos\nStudent No: 12345"));
        $member = User::factory()->create();
        $submission = $this->submit($member);

        $this->assertSame('pending', $submission->status);
        $this->assertSame(0.63, $submission->face_similarity);
        $this->assertSame('Maria Elena Santos', $submission->extracted_name);
        $this->assertNotSame('Maria Elena Santos', $submission->getRawOriginal('extracted_name'));
        $this->assertArrayNotHasKey('extracted_name', $submission->toArray());
        $this->assertNotNull($submission->consented_at);
        $this->assertFalse($member->fresh()->hasVerifiedIdentity());
        foreach (['front_path', 'back_path', 'selfie_path'] as $field) {
            Storage::disk('local')->assertExists($submission->$field);
            $this->assertStringStartsWith('identity-documents/', $submission->$field);
            $this->assertArrayNotHasKey($field, $submission->toArray());
        }
        $this->assertArrayNotHasKey('profile_hash', $submission->toArray());
    }

    public function test_duplicate_pending_submission_is_rejected_without_leaving_extra_files(): void
    {
        $member = User::factory()->create();
        $this->submit($member);
        try {
            $this->submit($member);
            $this->fail('A pending submission must block another upload.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('identity', $exception->errors());
        }
        $this->assertDatabaseCount('identity_submissions', 1);
        $this->assertCount(3, Storage::disk('local')->allFiles());
    }

    public function test_ineligible_members_cannot_submit(): void
    {
        foreach ([User::factory()->unverified()->create(), User::factory()->suspended()->create(),
            User::factory()->admin()->create(), User::factory()->create([
                'identity_verified_at' => now(), 'identity_verification_environment' => 'manual',
            ])] as $member) {
            try {
                $this->submit($member);
                $this->fail('Only an eligible unverified member may submit.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('identity', $exception->errors());
            }
        }
        $this->assertDatabaseCount('identity_submissions', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_consent_type_size_and_dimensions_are_validated_before_face_comparison(): void
    {
        $this->mock(FaceComparison::class)->shouldNotReceive('compare');
        $member = User::factory()->create();
        $invalidFronts = [UploadedFile::fake()->create('front.svg', 10, 'image/svg+xml'),
            UploadedFile::fake()->image('small.jpg', 100, 100),
            UploadedFile::fake()->image('large.jpg', 640, 480)->size(8193)];
        foreach ($invalidFronts as $front) {
            try {
                app(ManualIdentityReview::class)->submit($member, $front, null,
                    UploadedFile::fake()->image('selfie.jpg', 640, 480), true);
                $this->fail('Invalid image must be rejected.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('front', $exception->errors());
            }
        }
        try {
            app(ManualIdentityReview::class)->submit($member, UploadedFile::fake()->image('front.jpg', 640, 480),
                null, UploadedFile::fake()->image('selfie.jpg', 640, 480), false);
            $this->fail('Consent is required.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('consent', $exception->errors());
        }
        foreach (['', 'unrecognized'] as $type) {
            try {
                $this->submit($member, $type);
                $this->fail('A supported ID type is required.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('document_type', $exception->errors());
            }
        }
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_face_service_failure_removes_uploaded_files_without_creating_a_submission(): void
    {
        $this->mock(FaceComparison::class)->shouldReceive('compare')->once()
            ->andThrow(ValidationException::withMessages(['selfie' => 'One clear face is required.']));
        try {
            $this->submit();
            $this->fail('A failed face comparison must stop submission.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('selfie', $exception->errors());
        }
        $this->assertDatabaseCount('identity_submissions', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_invalid_face_scores_fail_closed_and_remove_files(): void
    {
        foreach ([NAN, INF, -1.01, 1.01] as $score) {
            $this->mock(FaceComparison::class)->shouldReceive('compare')->once()->andReturn($score);
            try {
                $this->submit();
                $this->fail('A face score must be finite and within the cosine range.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('identity', $exception->errors());
            }
        }
        $this->assertDatabaseCount('identity_submissions', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_approval_requires_each_confirmation_then_records_the_reviewer_and_purges_images(): void
    {
        Process::fake(fn () => Process::result(output: 'Name: Maria Elena Santos'));
        $submission = $this->submit();
        $admin = User::factory()->admin()->create();
        foreach ([[false, true, true], [true, false, true], [true, true, false]] as $checks) {
            try {
                app(ManualIdentityReview::class)->review($admin, $submission->id, 'approved', 'Checked official result.', ...$checks);
                $this->fail('Every approval confirmation is required.');
            } catch (ValidationException) {
                $this->assertSame('pending', $submission->fresh()->status);
            }
        }
        $reviewed = app(ManualIdentityReview::class)->review($admin, $submission->id, 'approved', 'Checked official result.', true, true, true);

        $this->assertSame('approved', $reviewed->status);
        $this->assertSame('national_id_qr', $reviewed->verification_method);
        $this->assertSame($admin->id, $reviewed->reviewed_by);
        $this->assertNotNull($reviewed->reviewed_at);
        $this->assertTrue($reviewed->user->hasVerifiedIdentity());
        $this->assertSame('manual-review:'.$submission->id, $reviewed->user->identity_reference);
        $this->assertNotSame($reviewed->user->identity_reference, $reviewed->user->getRawOriginal('identity_reference'));
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertNull($reviewed->fresh()->extracted_name);

        $this->expectException(ValidationException::class);
        app(ManualIdentityReview::class)->review($admin, $submission->id, 'rejected', 'Duplicate decision.');
    }

    public function test_other_photo_id_needs_a_recorded_issuer_check_before_approval(): void
    {
        $submission = $this->submit(documentType: 'other_photo_id');
        $admin = User::factory()->admin()->create();
        $review = app(ManualIdentityReview::class);

        foreach (['', 'national_id_qr', 'appearance_only'] as $method) {
            try {
                $review->review($admin, $submission->id, 'approved', '', true, true, true, $method);
                $this->fail('An issuer check is required for approval.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('issuer_check', $exception->errors());
            }
        }

        $this->assertSame('pending', $submission->fresh()->status);
        $this->assertFalse($submission->user->hasVerifiedIdentity());
        $reviewed = $review->review($admin, $submission->id, 'approved', '', true, true, true, 'issuer_confirmation');
        $this->assertSame('other_photo_id', $reviewed->document_type);
        $this->assertSame('issuer_confirmation', $reviewed->verification_method);
        $this->assertTrue($reviewed->user->hasVerifiedIdentity());
    }

    public function test_rejection_requires_a_reason_and_preserves_only_audit_metadata(): void
    {
        $submission = $this->submit();
        $admin = User::factory()->admin()->create();
        try {
            app(ManualIdentityReview::class)->review($admin, $submission->id, 'rejected', ' ');
            $this->fail('Rejection requires a reason.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('notes', $exception->errors());
        }
        $reviewed = app(ManualIdentityReview::class)->review($admin, $submission->id, 'rejected', 'QR details do not match.');
        $this->assertSame('rejected', $reviewed->status);
        $this->assertSame('QR details do not match.', $reviewed->review_notes);
        $this->assertFalse($reviewed->user->hasVerifiedIdentity());
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('identity_submissions', 1);
    }

    public function test_review_uses_current_admin_authorization_and_rejects_self_review(): void
    {
        $submission = $this->submit();
        $admin = User::factory()->admin()->create();
        User::whereKey($admin->id)->update(['status' => 'suspended']);
        foreach ([$admin, User::factory()->create(), User::factory()->admin()->unverified()->create()] as $viewer) {
            try {
                app(ManualIdentityReview::class)->review($viewer, $submission->id, 'approved', '', true, true, true);
                $this->fail('Current active verified admin authorization is required.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
        $member = $submission->user;
        $member->forceFill(['role' => 'admin'])->save();
        $this->expectException(HttpException::class);
        app(ManualIdentityReview::class)->review($member, $submission->id, 'approved', '', true, true, true);
    }

    public function test_stale_profile_cannot_be_approved_even_if_a_model_hook_was_bypassed(): void
    {
        $submission = $this->submit();
        User::whereKey($submission->user_id)->update(['name' => 'Changed legal name']);
        try {
            app(ManualIdentityReview::class)->review(User::factory()->admin()->create(), $submission->id, 'approved', '', true, true, true);
            $this->fail('The reviewed documents must match the submitted profile snapshot.');
        } catch (ValidationException) {
            $this->assertFalse($submission->user()->first()->hasVerifiedIdentity());
        }
    }

    public function test_profile_changes_suspension_and_deletion_cancel_pending_reviews_and_purge_images(): void
    {
        foreach (['name', 'email', 'suspension', 'deletion'] as $change) {
            $submission = $this->submit();
            $member = $submission->user;
            match ($change) {
                'name' => $member->update(['name' => 'Corrected legal name']),
                'email' => $member->update(['email' => 'new-'.Str::random(8).'@example.test']),
                'suspension' => $member->update(['status' => 'suspended']),
                'deletion' => $member->delete(),
            };
            $this->assertSame('cancelled', $submission->fresh()->status);
            $this->assertSame([], Storage::disk('local')->allFiles());
        }
    }

    public function test_rollback_keeps_pending_documents_and_does_not_grant_verification(): void
    {
        $submission = $this->submit();
        $admin = User::factory()->admin()->create();
        DB::beginTransaction();
        try {
            app(ManualIdentityReview::class)->review($admin, $submission->id, 'approved', '', true, true, true);
            Storage::disk('local')->assertExists($submission->front_path);
        } finally {
            DB::rollBack();
        }
        $this->assertSame('pending', $submission->fresh()->status);
        $this->assertFalse($submission->user()->first()->hasVerifiedIdentity());
        $this->assertCount(3, Storage::disk('local')->allFiles());
    }

    public function test_documents_are_only_visible_to_the_current_owner_or_active_verified_admin_while_pending(): void
    {
        $submission = $this->submit();
        $url = '/_test/identity-documents/'.$submission->id.'/front';
        $owner = $submission->user;
        $this->actingAs($owner)->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Content-Type', 'image/jpeg');
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get($url)->assertOk();
        foreach ([User::factory()->create(), User::factory()->admin()->suspended()->create(),
            User::factory()->admin()->unverified()->create()] as $viewer) {
            $this->actingAs($viewer)->get($url)->assertForbidden();
        }
        $this->actingAs($owner)->get('/_test/identity-documents/'.$submission->id.'/profile_hash')->assertNotFound();
        User::whereKey($owner->id)->update(['status' => 'suspended']);
        $this->actingAs($owner)->get($url)->assertForbidden();
        User::whereKey($owner->id)->update(['status' => 'active']);
        app(ManualIdentityReview::class)->review($admin, $submission->id, 'rejected', 'Unreadable QR code.');
        $this->actingAs($admin)->get($url)->assertNotFound();
    }

    public function test_expired_pending_photos_are_purged_while_recent_photos_are_preserved(): void
    {
        $old = $this->submit();
        $old->update(['created_at' => now()->subDays(30)]);
        $recent = $this->submit();
        $this->artisan('identity:prune-documents')->assertSuccessful();
        $this->assertSame('cancelled', $old->fresh()->status);
        $this->assertNull($old->fresh()->front_path);
        $this->assertSame('pending', $recent->fresh()->status);
        Storage::disk('local')->assertExists($recent->front_path);
        $this->assertCount(3, Storage::disk('local')->allFiles());
    }

    public function test_repeated_face_processing_failures_are_rate_limited(): void
    {
        $member = User::factory()->create();
        $this->mock(FaceComparison::class)->shouldReceive('compare')->times(5)
            ->andThrow(ValidationException::withMessages(['selfie' => 'No clear face.']));
        for ($i = 0; $i < 5; $i++) {
            try {
                $this->submit($member);
                $this->fail('Invalid photo accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('selfie', $exception->errors());
            }
        }
        try {
            $this->submit($member);
            $this->fail('Processing should be rate limited.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('identity', $exception->errors());
        }
        $this->assertSame([], Storage::disk('local')->allFiles());
    }
}
