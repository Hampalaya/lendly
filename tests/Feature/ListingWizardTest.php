<?php

namespace Tests\Feature;

use App\Livewire\Owner\Listings\Form;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ListingWizardTest extends TestCase
{
    use RefreshDatabase;

    private function form()
    {
        Storage::fake('public');

        return Livewire::actingAs(User::factory()->owner()->identityVerified()->create())->test(Form::class);
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('item.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aDd8AAAAASUVORK5CYII='
        ));
    }

    public function test_steps_validate_their_own_fields_and_cannot_be_skipped(): void
    {
        $this->form()->call('goToStep', 5)->assertSet('step', 1)
            ->call('submitStep')->assertSet('step', 1)
            ->assertHasErrors(['category_id', 'name', 'description'])
            ->assertHasNoErrors(['photos', 'price_per_day', 'latitude'])
            ->set('category_id', 0)->set('other_category', '  Camping   gear  ')
            ->set('name', 'Tent')->set('description', 'A two-person tent.')
            ->call('submitStep')->assertHasNoErrors()->assertSet('step', 2)
            ->assertSet('other_category', 'Camping gear')->assertSet('furthestStep', 2)
            ->call('submitStep')->assertSet('step', 2)->assertHasErrors(['photos']);

        $this->assertDatabaseCount('listings', 0);
        $this->assertDatabaseCount('categories', 0);
    }

    public function test_back_navigation_keeps_photos_and_rechecks_edited_details(): void
    {
        $category = Category::create(['name' => 'Tools']);
        $form = $this->form()->set('category_id', $category->id)->set('name', 'Drill')->set('description', 'Cordless drill')
            ->call('nextStep')->set('photoBatch', [$this->photo()])->call('nextStep')
            ->assertHasNoErrors()->assertSet('step', 3)
            ->call('goToStep', 1)->assertSet('name', 'Drill')
            ->set('description', '')->call('goToStep', 3)
            ->assertSet('step', 1)->assertHasErrors(['description']);

        $this->assertCount(1, $form->get('photos'));
        $form->set('description', 'Updated description')->call('goToStep', 3)
            ->assertHasNoErrors()->assertSet('step', 3);
    }

    public function test_review_contains_all_details_and_only_final_submission_publishes(): void
    {
        $category = Category::create(['name' => 'Tools']);
        $form = $this->form()->set('category_id', $category->id)->set('name', 'Review drill')->set('description', 'Cordless drill')
            ->call('submitStep')->set('photoBatch', [$this->photo()])->call('submitStep')
            ->set('price_per_day', 100)->set('available_from', now()->toDateString())
            ->set('available_until', now()->addWeek()->toDateString())
            ->set('allows_daily_rentals', false)->call('submitStep')
            ->assertSet('step', 3)->assertHasErrors(['price_per_hour'])
            ->set('price_per_hour', 25)->call('submitStep')
            ->assertHasNoErrors()->assertSet('step', 4)
            ->set('location', 'Manila')->set('latitude', 0)->set('longitude', 0)
            ->set('pickup_available', false)->call('submitStep')->assertHasErrors(['pickup_available'])
            ->set('delivery_available', true)->set('rental_rules', 'Return it clean.')
            ->call('submitStep')->assertHasNoErrors()->assertSet('step', 5)
            ->assertSee('Review Your Listing')->assertSee('Review drill')->assertSee('Return it clean.')
            ->assertSee('Publish Listing');

        $this->assertDatabaseCount('listings', 0);
        $form->call('submitStep')->assertHasNoErrors()->assertRedirect(route('owner.listings.index'));
        $listing = Listing::firstOrFail();
        $this->assertSame('Review drill', $listing->name);
        $this->assertCount(1, $listing->images);
        $this->assertFalse($listing->pickup_available);
        $this->assertTrue($listing->delivery_available);
        Storage::disk('public')->assertExists($listing->images->first()->path);
    }

    public function test_final_validation_opens_the_step_containing_the_first_error(): void
    {
        $category = Category::create(['name' => 'Tools']);
        $this->form()->set('category_id', $category->id)->set('name', 'Drill')->set('description', 'Cordless drill')
            ->call('nextStep')->set('photoBatch', [$this->photo()])->call('nextStep')
            ->set('price_per_day', 100)->set('available_from', now()->toDateString())
            ->set('available_until', now()->addWeek()->toDateString())->call('nextStep')
            ->set('location', 'Manila')->set('latitude', 0)->set('longitude', 0)->call('nextStep')
            ->assertSet('step', 5)->set('available_until', now()->subDay()->toDateString())
            ->call('submitStep')->assertSet('step', 3)->assertHasErrors(['available_until']);
        $this->assertDatabaseCount('listings', 0);
    }
}
