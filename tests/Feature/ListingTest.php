<?php

namespace Tests\Feature;

use App\Enums\ListingStatus;
use App\Livewire\Admin\Listings\Index as AdminListingsIndex;
use App\Livewire\Owner\Listings\Form as OwnerListingForm;
use App\Livewire\Owner\Listings\Index as OwnerListingsIndex;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ListingTest extends TestCase
{
    use RefreshDatabase;

    private function makeCategory(): Category
    {
        return Category::create(['name' => 'Tools', 'slug' => 'tools']);
    }

    public function test_owner_can_create_a_listing_and_it_is_published_immediately(): void
    {
        Storage::fake('public');
        $owner = User::factory()->identityVerified()->owner()->create();
        $category = $this->makeCategory();

        Livewire::actingAs($owner)
            ->test(OwnerListingForm::class)
            ->set('category_id', $category->id)
            ->set('name', 'Pressure Washer')
            ->set('description', 'Great for cleaning driveways.')
            ->set('price_per_day', 450)
            ->set('security_deposit', 1000)
            ->set('location', 'Nasugbu')
            ->set('latitude', 14.07)
            ->set('longitude', 120.63)
            ->set('available_from', now()->toDateString())
            ->set('available_until', now()->addMonth()->toDateString())
            ->set('photos', [UploadedFile::fake()->createWithContent('washer.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='))])
            ->set('max_rental_duration_days', 7)
            ->call('save')
            ->assertHasNoErrors();

        $listing = Listing::firstWhere('name', 'Pressure Washer');

        $this->assertNotNull($listing);
        $this->assertSame(ListingStatus::Published, $listing->status);
        $this->assertSame($owner->id, $listing->owner_id);
    }

    public function test_listing_requires_price_greater_than_zero(): void
    {
        $owner = User::factory()->identityVerified()->owner()->create();
        $category = $this->makeCategory();

        Livewire::actingAs($owner)
            ->test(OwnerListingForm::class)
            ->set('category_id', $category->id)
            ->set('name', 'Free Item')
            ->set('description', 'Should not be allowed.')
            ->set('price_per_day', 0)
            ->set('location', 'Manila')
            ->set('max_rental_duration_days', 7)
            ->call('save')
            ->assertHasErrors(['price_per_day']);
    }

    public function test_listing_requires_category_and_location(): void
    {
        $owner = User::factory()->identityVerified()->owner()->create();

        Livewire::actingAs($owner)
            ->test(OwnerListingForm::class)
            ->set('name', 'Some Item')
            ->set('description', 'Missing category and location.')
            ->set('price_per_day', 100)
            ->call('save')
            ->assertHasErrors(['category_id', 'location']);
    }

    public function test_security_deposit_cannot_be_negative(): void
    {
        $owner = User::factory()->identityVerified()->owner()->create();
        $category = $this->makeCategory();

        Livewire::actingAs($owner)
            ->test(OwnerListingForm::class)
            ->set('category_id', $category->id)
            ->set('name', 'Some Item')
            ->set('description', 'Negative deposit test.')
            ->set('price_per_day', 100)
            ->set('security_deposit', -50)
            ->set('location', 'Manila')
            ->set('max_rental_duration_days', 7)
            ->call('save')
            ->assertHasErrors(['security_deposit']);
    }

    public function test_any_member_can_access_owner_listing_management(): void
    {
        $member = User::factory()->identityVerified()->renter()->create();

        $this->actingAs($member)->get(route('owner.listings.index'))->assertOk();
        $this->actingAs($member)->get(route('owner.listings.create'))->assertOk();
    }

    public function test_admin_cannot_access_owner_listing_management(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('owner.listings.index'))->assertForbidden();
    }

    public function test_owner_cannot_edit_another_owners_listing(): void
    {
        $ownerA = User::factory()->identityVerified()->owner()->create();
        $ownerB = User::factory()->identityVerified()->owner()->create();
        $category = $this->makeCategory();

        $listing = Listing::create([
            'owner_id' => $ownerA->id,
            'category_id' => $category->id,
            'name' => 'Owner A Item',
            'description' => 'Belongs to owner A.',
            'condition' => 'good',
            'price_per_day' => 100,
            'location' => 'Manila',
            'max_rental_duration_days' => 7,
        ]);

        $this->actingAs($ownerB)
            ->get(route('owner.listings.edit', $listing))
            ->assertForbidden();
    }

    public function test_pending_and_rejected_listings_are_not_publicly_visible(): void
    {
        $owner = User::factory()->identityVerified()->owner()->create();
        $guest = User::factory()->identityVerified()->renter()->create();
        $category = $this->makeCategory();

        $listing = Listing::create([
            'owner_id' => $owner->id,
            'category_id' => $category->id,
            'name' => 'Pending Item',
            'description' => 'Awaiting approval.',
            'condition' => 'good',
            'price_per_day' => 100,
            'location' => 'Manila',
            'max_rental_duration_days' => 7,
        ]);

        $this->actingAs($guest)->get(route('listings.show', $listing))->assertForbidden();

        $listing->update(['status' => ListingStatus::Rejected, 'rejection_reason' => 'Blurry photos.']);
        $this->actingAs($guest)->get(route('listings.show', $listing))->assertForbidden();

        // Owner and admin can still view their own / any listing regardless of status.
        $this->actingAs($owner)->get(route('listings.show', $listing))->assertOk();
    }

    public function test_admin_can_approve_a_pending_listing_making_it_publicly_visible(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->identityVerified()->owner()->create();
        $renter = User::factory()->identityVerified()->renter()->create();
        $category = $this->makeCategory();

        $listing = Listing::create([
            'owner_id' => $owner->id,
            'category_id' => $category->id,
            'name' => 'Ladder',
            'description' => 'Aluminum ladder.',
            'condition' => 'good',
            'price_per_day' => 100,
            'location' => 'Manila',
            'max_rental_duration_days' => 7,
        ]);

        Livewire::actingAs($admin)
            ->test(AdminListingsIndex::class)
            ->call('approve', $listing->id);

        $this->assertSame(ListingStatus::Published, $listing->fresh()->status);
        $this->actingAs($renter)->get(route('listings.show', $listing))->assertOk();
    }

    public function test_admin_can_reject_a_listing_with_a_reason(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->identityVerified()->owner()->create();
        $category = $this->makeCategory();

        $listing = Listing::create([
            'owner_id' => $owner->id,
            'category_id' => $category->id,
            'name' => 'Blurry Photo Item',
            'description' => 'Bad photos.',
            'condition' => 'good',
            'price_per_day' => 100,
            'location' => 'Manila',
            'max_rental_duration_days' => 7,
        ]);

        Livewire::actingAs($admin)
            ->test(AdminListingsIndex::class)
            ->set('filter', 'pending_approval')
            ->assertSee('Reject Blurry Photo Item')
            ->call('startRejecting', $listing->id)
            ->assertSee('Reason for rejection')
            ->set('rejection_reason', 'Photos are too blurry to review.')
            ->call('confirmReject');

        $listing->refresh();

        $this->assertSame(ListingStatus::Rejected, $listing->status);
        $this->assertSame('Photos are too blurry to review.', $listing->rejection_reason);
    }

    public function test_owner_can_deactivate_and_reactivate_their_own_listing(): void
    {
        $owner = User::factory()->identityVerified()->owner()->create();
        $category = $this->makeCategory();

        $listing = Listing::create([
            'owner_id' => $owner->id,
            'category_id' => $category->id,
            'name' => 'Camera',
            'description' => 'DSLR camera.',
            'condition' => 'good',
            'price_per_day' => 100,
            'location' => 'Manila',
            'max_rental_duration_days' => 7,
            'status' => ListingStatus::Published,
            'latitude' => 14.07,
            'longitude' => 120.63,
            'available_from' => now()->toDateString(),
            'available_until' => now()->addMonth()->toDateString(),
        ]);
        $listing->images()->create(['path' => 'listings/camera.jpg', 'sort_order' => 0]);

        Livewire::actingAs($owner)
            ->test(OwnerListingsIndex::class)
            ->call('deactivate', $listing->id);

        $this->assertSame(ListingStatus::Inactive, $listing->fresh()->status);

        Livewire::actingAs($owner)
            ->test(OwnerListingsIndex::class)
            ->call('reactivate', $listing->id);

        $this->assertSame(ListingStatus::Published, $listing->fresh()->status);
    }

    public function test_owner_can_set_availability_without_changing_approval_status(): void
    {
        $owner = User::factory()->identityVerified()->owner()->create();
        $category = $this->makeCategory();

        foreach (ListingStatus::cases() as $status) {
            $listing = Listing::create([
                'owner_id' => $owner->id,
                'category_id' => $category->id,
                'name' => 'Availability test item',
                'description' => 'An item with independently managed availability.',
                'condition' => 'good',
                'price_per_day' => 100,
                'location' => 'Manila',
                'max_rental_duration_days' => 7,
                'status' => $status,
                'is_available' => true,
            ]);

            $component = Livewire::actingAs($owner)->test(OwnerListingsIndex::class);
            $component->call('setAvailability', $listing->id, false)->assertHasNoErrors();

            $this->assertFalse($listing->fresh()->is_available);
            $this->assertSame($status, $listing->fresh()->status);

            $component->call('setAvailability', $listing->id, true)->assertHasNoErrors();
            // An explicit choice is safe to repeat without toggling the item back off.
            $component->call('setAvailability', $listing->id, true)->assertHasNoErrors();

            $this->assertTrue($listing->fresh()->is_available);
            $this->assertSame($status, $listing->fresh()->status);
        }
    }

    public function test_owner_cannot_change_another_owners_availability(): void
    {
        $owner = User::factory()->identityVerified()->owner()->create();
        $otherOwner = User::factory()->identityVerified()->owner()->create();
        $category = $this->makeCategory();
        $listing = Listing::create([
            'owner_id' => $owner->id,
            'category_id' => $category->id,
            'name' => 'Private owner item',
            'description' => 'Belongs to another owner.',
            'condition' => 'good',
            'price_per_day' => 100,
            'location' => 'Manila',
            'max_rental_duration_days' => 7,
            'status' => ListingStatus::Published,
            'is_available' => true,
        ]);

        Livewire::actingAs($otherOwner)
            ->test(OwnerListingsIndex::class)
            ->call('setAvailability', $listing->id, false)
            ->assertForbidden();

        $this->assertTrue($listing->fresh()->is_available);
    }

    public function test_owner_cannot_deactivate_another_owners_listing(): void
    {
        $ownerA = User::factory()->identityVerified()->owner()->create();
        $ownerB = User::factory()->identityVerified()->owner()->create();
        $category = $this->makeCategory();

        $listing = Listing::create([
            'owner_id' => $ownerA->id,
            'category_id' => $category->id,
            'name' => 'Drill',
            'description' => 'Cordless drill.',
            'condition' => 'good',
            'price_per_day' => 100,
            'location' => 'Manila',
            'max_rental_duration_days' => 7,
            'status' => ListingStatus::Published,
        ]);

        Livewire::actingAs($ownerB)
            ->test(OwnerListingsIndex::class)
            ->call('deactivate', $listing->id)
            ->assertForbidden();
    }
}
