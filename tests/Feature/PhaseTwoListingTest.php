<?php

namespace Tests\Feature;

use App\Enums\ListingStatus;
use App\Livewire\Owner\Listings\Form;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use App\Services\ListingAddress;
use App\Services\ListingCategories;
use App\Services\MarketInsight;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PhaseTwoListingTest extends TestCase
{
    use RefreshDatabase;

    private function form()
    {
        Storage::fake('public');

        return Livewire::actingAs(User::factory()->owner()->identityVerified()->create())->test(Form::class)
            ->set('name', 'Portable telescope')->set('description', 'With carrying case')
            ->set('price_per_day', 200)->set('location', 'Manila')->set('latitude', 0)->set('longitude', 0)
            ->set('available_from', now()->toDateString())->set('available_until', now()->addMonth()->toDateString());
    }

    public function test_custom_category_and_subcategory_save_and_load_in_edit_form(): void
    {
        $this->form()->set('category_id', 0)->set('other_category', '  Science   gear  ')
            ->set('subcategory_id', 0)->set('other_subcategory', 'Telescopes')
            ->set('photoBatch', [UploadedFile::fake()->image('front.jpg')])->call('save')->assertHasNoErrors();
        $listing = Listing::firstOrFail();
        $this->assertSame('Science gear', $listing->category->name);
        $this->assertSame('Telescopes', $listing->subcategory->name);
        $this->assertSame($listing->category_id, $listing->subcategory->parent_id);
        Livewire::test(Form::class, ['listing' => $listing])->assertSet('category_id', $listing->category_id)
            ->assertSet('subcategory_id', $listing->subcategory_id);
    }

    public function test_custom_names_reuse_existing_categories_case_insensitively_within_parent(): void
    {
        $root = Category::create(['name' => 'Science gear']);
        $child = Category::create(['name' => 'Telescopes', 'parent_id' => $root->id]);
        $this->assertSame($root->id, ListingCategories::resolve(' SCIENCE  gear ')->id);
        $this->assertSame($child->id, ListingCategories::resolve(' telescopes ', $root->id)->id);
        $other = ListingCategories::resolve('Other parent');
        $this->assertNotSame($child->id, ListingCategories::resolve('Telescopes', $other->id)->id);
    }

    public function test_blank_other_fields_do_not_create_categories_or_publish(): void
    {
        $this->form()->set('category_id', 0)->set('other_category', '   ')->set('subcategory_id', 0)
            ->call('save')->assertHasErrors(['other_category', 'other_subcategory']);
        $this->assertDatabaseCount('categories', 0);
        $this->assertDatabaseCount('listings', 0);
    }

    public function test_subcategory_must_belong_to_selected_parent(): void
    {
        $first = Category::create(['name' => 'First']);
        $second = Category::create(['name' => 'Second']);
        $child = Category::create(['name' => 'Second child', 'parent_id' => $second->id]);
        $this->form()->set('category_id', $first->id)->set('subcategory_id', $child->id)
            ->set('photos', [UploadedFile::fake()->image('item.jpg')])->call('save')->assertHasErrors(['subcategory_id']);
        $this->assertDatabaseCount('listings', 0);
    }

    public function test_photo_batches_accumulate_remove_and_save_all_remaining_files(): void
    {
        $root = Category::create(['name' => 'Tools']);
        $form = $this->form()->set('category_id', $root->id)
            ->set('photoBatch', [UploadedFile::fake()->image('front.jpg')])
            ->set('photoBatch', [UploadedFile::fake()->image('back.jpg'), UploadedFile::fake()->image('case.jpg')]);
        $this->assertCount(3, $form->get('photos'));
        $form->assertSet('photoBatch', [])->assertSee('3 / 20 photos selected')->call('removePendingPhoto', 1);
        $this->assertCount(2, $form->get('photos'));
        $form->call('save')->assertHasNoErrors();
        $listing = Listing::firstOrFail();
        $this->assertSame(2, $listing->images()->count());
        foreach ($listing->images as $image) {
            Storage::disk('public')->assertExists($image->path);
        }
    }

    public function test_invalid_or_excess_photo_batches_keep_earlier_photos(): void
    {
        $form = $this->form()->set('photoBatch', [UploadedFile::fake()->image('front.jpg')]);
        $form->set('photoBatch', [UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')])->assertHasErrors(['photoBatch.0']);
        $this->assertCount(1, $form->get('photos'));
        $form->set('photoBatch', array_map(fn ($i) => UploadedFile::fake()->image("item-$i.jpg"), range(1, 20)))
            ->assertHasErrors(['photoBatch']);
        $this->assertCount(1, $form->get('photos'));
    }

    public function test_original_price_changes_suggestion_without_overwriting_owner_rate(): void
    {
        $root = Category::create(['name' => 'Tools']);
        Listing::create(['owner_id' => User::factory()->create()->id, 'category_id' => $root->id,
            'name' => 'Similar', 'description' => 'Comparable', 'condition' => 'good', 'price_per_day' => 300,
            'estimated_original_price' => 10000, 'location' => 'Manila', 'status' => ListingStatus::Published]);
        $form = $this->form()->set('category_id', $root->id)->set('estimated_original_price', 20000)->assertSet('price_per_day', 200.0);
        $this->assertEquals(600, $form->instance()->marketInsight()['suggested']);
        $form->set('estimated_original_price', 30000)->assertSet('price_per_day', 200.0);
        $this->assertEquals(900, $form->instance()->marketInsight()['suggested']);
        $form->call('useSuggestedPrice')->assertSet('price_per_day', 900.0);
        $empty = MarketInsight::forListing(null, null, originalPrice: 10000);
        $this->assertSame('starting-estimate', $empty['basis']);
        $this->assertEquals(100, $empty['suggested']);
        $this->assertNull(MarketInsight::forListing(null, null)['suggested']);
    }

    public function test_edit_preserves_inspection_flag_and_existing_photos(): void
    {
        $root = Category::create(['name' => 'Tools']);
        $this->form()->set('category_id', $root->id)->set('photos', [UploadedFile::fake()->image('front.jpg')])->call('save')->assertHasNoErrors();
        $listing = Listing::firstOrFail();
        $listing->update(['reopen_required' => true, 'is_available' => false]);
        Livewire::test(Form::class, ['listing' => $listing])->set('name', 'Updated telescope')
            ->set('photoBatch', [UploadedFile::fake()->image('case.jpg')])->call('save')->assertHasNoErrors();
        $this->assertTrue($listing->fresh()->reopen_required);
        $this->assertFalse($listing->fresh()->is_available);
        $this->assertSame(2, $listing->images()->count());
    }

    public function test_original_price_shows_live_insight_inside_item_details_without_changing_the_rate(): void
    {
        $form = $this->form()->assertSeeInOrder(['Original price', 'Market insight', 'Location & logistics', 'Pricing & deposit'])
            ->assertSee('Enter the original price above to get a suggested daily rental rate.');
        $form->set('estimated_original_price', 10000)->assertSee('10,000.00')->assertSee('Suggested daily rate: ₱100.00')
            ->assertSee('Starting estimate: 1%')->assertSet('price_per_day', 200.0);
        $form->set('estimated_original_price', 20000)->assertSee('20,000.00')->assertSee('Suggested daily rate: ₱200.00')
            ->set('estimated_original_price', 30000)->assertSee('Suggested daily rate: ₱300.00')->assertSet('price_per_day', 200.0)
            ->call('useSuggestedPrice')->assertSet('price_per_day', 300.0)
            ->set('estimated_original_price', null)->assertSee('Enter the original price above to get a suggested daily rental rate.');
    }

    public function test_address_lookup_formats_caches_and_throttles_provider_calls(): void
    {
        Cache::flush();
        Http::fake(['*' => Http::response(['features' => [['properties' => ['housenumber' => '12', 'street' => 'Test Street', 'city' => 'Manila', 'country' => 'Philippines']]]])]);
        $service = app(ListingAddress::class);
        $this->assertSame('12 Test Street, Manila, Philippines', $service->lookup(0, 0));
        $this->assertSame('12 Test Street, Manila, Philippines', $service->lookup(0, 0));
        $this->assertNull($service->lookup(1, 1));
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['lat'] == 0 && $request['lon'] == 0);
    }

    public function test_address_provider_failure_and_empty_results_allow_manual_entry(): void
    {
        foreach ([Http::response([], 503), Http::response(['features' => []]), Http::failedConnection()] as $response) {
            Cache::flush();
            Http::fake(['*' => $response]);
            $this->assertNull(app(ListingAddress::class)->lookup(14.5995, 120.9842));
        }
    }

    public function test_busy_provider_reports_retry_and_then_resolves_without_caching_the_busy_result(): void
    {
        Cache::flush();
        Http::fake(['*' => Http::response(['features' => [['properties' => ['city' => 'Manila', 'country' => 'Philippines']]]])]);
        Cache::put('listing-address-provider-slot', true, 1);
        $service = app(ListingAddress::class);
        $this->assertSame(['address' => null, 'retry_after' => 1], $service->resolve(14.6, 121));
        Http::assertNothingSent();
        $this->travel(2)->seconds();
        $this->assertSame(['address' => 'Manila, Philippines'], $service->resolve(14.6, 121));
        $this->assertSame(['address' => 'Manila, Philippines'], $service->resolve(14.6, 121));
        Http::assertSentCount(1);
    }

    public function test_owner_pin_action_passes_retry_information_and_keeps_manual_input(): void
    {
        Cache::flush();
        Http::fake();
        Cache::put('listing-address-provider-slot', true, 1);
        session(['active_interface' => 'owner']);
        Livewire::actingAs(User::factory()->owner()->create())->test(Form::class)
            ->assertSee('Use current location')->set('location', 'Manual pickup gate')
            ->call('resolvePinnedAddress', 14.6, 121)->assertReturned(['address' => null, 'retry_after' => 1])
            ->assertSet('location', 'Manual pickup gate');
        Http::assertNothingSent();
    }

    public function test_address_action_requires_owner_interface_and_valid_coordinates(): void
    {
        Http::fake(['*' => Http::response(['features' => []])]);
        $owner = User::factory()->owner()->create();
        Livewire::actingAs($owner)->test(Form::class)->call('resolvePinnedAddress', 0, 0)->assertForbidden();
        session(['active_interface' => 'owner']);
        Livewire::actingAs($owner)->test(Form::class)->call('resolvePinnedAddress', 91, 0)->assertHasErrors(['latitude']);
        Http::assertNothingSent();
        Livewire::actingAs($owner)->test(Form::class)->set('location', 'My manual address')
            ->call('resolvePinnedAddress', 0, 0)->assertSet('location', 'My manual address')->assertHasNoErrors();
        Http::assertSentCount(1);
    }
}
