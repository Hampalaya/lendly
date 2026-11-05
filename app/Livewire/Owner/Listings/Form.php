<?php

namespace App\Livewire\Owner\Listings;

use App\Enums\ListingStatus;
use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Services\ListingAddress;
use App\Services\ListingCategories;
use App\Services\ListingProgress;
use App\Services\ListingPublication;
use App\Services\MarketInsight;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class Form extends Component
{
    use WithFileUploads;

    public ?Listing $listing = null;

    #[Locked]
    public int $step = 1;

    #[Locked]
    public int $furthestStep = 1;

    private const STEP_FIELDS = [
        1 => ['category_id', 'subcategory_id', 'other_category', 'other_subcategory', 'name', 'brand', 'model', 'description', 'condition', 'purchase_year', 'estimated_original_price'],
        2 => ['photos', 'photoBatch'],
        3 => ['price_per_day', 'price_per_hour', 'price_per_week', 'security_deposit', 'allows_daily_rentals', 'available_time_from', 'available_time_until', 'available_from', 'available_until', 'max_rental_duration_days', 'is_available'],
        4 => ['location', 'latitude', 'longitude', 'pickup_available', 'delivery_available', 'rental_rules'],
    ];

    public ?int $category_id = null;

    public ?int $subcategory_id = null;

    public string $other_category = '';

    public string $other_subcategory = '';

    public string $name = '';

    public string $brand = '';

    public string $model = '';

    public string $description = '';

    public string $condition = 'good';

    public ?int $purchase_year = null;

    public ?float $estimated_original_price = null;

    public ?float $price_per_day = null;

    public ?float $price_per_hour = null;

    public bool $allows_daily_rentals = true;

    public string $available_time_from = '00:00';

    public string $available_time_until = '23:59';

    public ?float $price_per_week = null;

    public float $security_deposit = 0;

    public string $location = '';

    public ?float $latitude = null;

    public ?float $longitude = null;

    public bool $pickup_available = true;

    public bool $delivery_available = false;

    public string $rental_rules = '';

    public ?int $max_rental_duration_days = 7;

    public bool $is_available = true;

    public string $available_from = '';

    public string $available_until = '';

    #[Locked]
    public array $removedImageIds = [];

    /** @var array<int, TemporaryUploadedFile> */
    public array $photos = [];

    /** Files selected in the next upload batch; accepted files append to photos. */
    public array $photoBatch = [];

    public function mount(?Listing $listing = null): void
    {
        if ($listing?->exists) {
            $this->authorize('update', $listing);

            $this->listing = $listing;
            $this->category_id = $listing->category_id;
            $this->subcategory_id = $listing->subcategory_id;
            $this->name = $listing->name;
            $this->brand = (string) $listing->brand;
            $this->model = (string) $listing->model;
            $this->description = $listing->description;
            $this->condition = $listing->condition->value;
            $this->purchase_year = $listing->purchase_year;
            $this->estimated_original_price = $listing->estimated_original_price ? (float) $listing->estimated_original_price : null;
            $this->allows_daily_rentals = $listing->allows_daily_rentals;
            $this->available_time_from = $listing->available_time_from;
            $this->available_time_until = $listing->available_time_until;
            $this->price_per_day = (float) $listing->price_per_day;
            $this->price_per_hour = $listing->price_per_hour ? (float) $listing->price_per_hour : null;
            $this->price_per_week = $listing->price_per_week ? (float) $listing->price_per_week : null;
            $this->security_deposit = (float) $listing->security_deposit;
            $this->location = $listing->location;
            $this->latitude = $listing->latitude !== null ? (float) $listing->latitude : null;
            $this->longitude = $listing->longitude !== null ? (float) $listing->longitude : null;
            $this->pickup_available = $listing->pickup_available;
            $this->delivery_available = $listing->delivery_available;
            $this->rental_rules = (string) $listing->rental_rules;
            $this->max_rental_duration_days = $listing->max_rental_duration_days;
            $this->is_available = $listing->is_available;
            $this->available_from = $listing->available_from?->toDateString() ?? '';
            $this->available_until = $listing->available_until?->toDateString() ?? '';
        }
    }

    public function subcategories(): Collection
    {
        if (! $this->category_id) {
            return collect();
        }

        return Category::where('parent_id', $this->category_id)->orderBy('name')->get();
    }

    public function updatedCategoryId(): void
    {
        $this->subcategory_id = null;
        $this->other_subcategory = '';
        if ($this->category_id !== 0) {
            $this->other_category = '';
        }
    }

    public function marketInsight(): array
    {
        return MarketInsight::forListing($this->category_id ?: null, $this->subcategory_id ?: null, $this->listing?->id, $this->estimated_original_price);
    }

    public function useSuggestedPrice(): void
    {
        if ($suggestion = $this->marketInsight()['suggested']) {
            $this->price_per_day = $suggestion;
        }
    }

    public function updatedPhotoBatch(): void
    {
        try {
            $this->validate(['photoBatch' => ['array', 'max:20'], 'photoBatch.*' => ['required', 'image', 'max:4096']]);
            if ($this->existingPhotoCount() + count($this->photos) + count($this->photoBatch) > 20) {
                throw ValidationException::withMessages(['photoBatch' => 'Keep up to 20 photos per listing. Remove a photo before adding more.']);
            }
            $this->photos = [...$this->photos, ...$this->photoBatch];
        } finally {
            $this->photoBatch = [];
        }
    }

    public function resolvePinnedAddress(float $latitude, float $longitude): array
    {
        $user = auth()->user();
        abort_unless($user && $user->isOwner() && ! $user->isSuspended() && $user->hasVerifiedEmail() && $user->activeInterface() === 'owner', 403);
        if ($this->listing?->exists) {
            $this->authorize('update', $this->listing);
        }
        validator(['latitude' => $latitude, 'longitude' => $longitude], [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ])->validate();
        $limit = 'listing-address-user:'.$user->id;
        if (RateLimiter::tooManyAttempts($limit, 30)) {
            return ['address' => null];
        }
        RateLimiter::hit($limit, 60);

        return app(ListingAddress::class)->resolve($latitude, $longitude);
    }

    public function isPriceSignificantlyHigh(): bool
    {
        $insight = $this->marketInsight();

        return $this->price_per_day && $insight['average']
            ? MarketInsight::isSignificantlyHigher((float) $this->price_per_day, $insight['average'])
            : false;
    }

    public function removeExistingImage(int $imageId): void
    {
        abort_unless($this->listing?->exists, 404);
        $this->authorize('update', $this->listing);
        abort_unless($this->listing->images()->whereKey($imageId)->exists(), 404);
        $this->removedImageIds = array_values(array_unique([...$this->removedImageIds, $imageId]));
    }

    public function removePendingPhoto(int $index): void
    {
        unset($this->photos[$index]);
        $this->photos = array_values($this->photos);
    }

    public function existingPhotoCount(): int
    {
        return $this->listing?->images()->whereNotIn('id', $this->removedImageIds)->count() ?? 0;
    }

    protected function publicationRules(): array
    {
        return [
            ...ListingPublication::rules($this->category_id),
            'category_id' => $this->category_id === 0 ? ['required', 'integer', 'in:0'] : ['required', Rule::exists('categories', 'id')->whereNull('parent_id')],
            'subcategory_id' => $this->subcategory_id === 0 ? ['nullable', 'integer', 'in:0'] : ['nullable', Rule::exists('categories', 'id')->where('parent_id', $this->category_id)],
            'other_category' => ['required_if:category_id,0', 'nullable', 'string', 'max:100'],
            'other_subcategory' => ['required_if:subcategory_id,0', 'nullable', 'string', 'max:100'],
            'photos' => ['array', 'max:20', 'min:'.($this->existingPhotoCount() > 0 ? 0 : 1)],
            'photos.*' => ['required', 'image', 'max:4096'],
        ];
    }

    protected function validateStep(int $step): void
    {
        $this->other_category = Str::squish($this->other_category);
        $this->other_subcategory = Str::squish($this->other_subcategory);
        $fields = self::STEP_FIELDS[$step];
        if ($step === 2) {
            $fields[] = 'photos.*';
        }
        $this->validate(array_intersect_key($this->publicationRules(), array_flip($fields)), ListingPublication::messages());

        if ($step === 2 && $this->existingPhotoCount() + count($this->photos) > 20) {
            throw ValidationException::withMessages(['photos' => 'Keep up to 20 photos per listing.']);
        }
        if ($step === 3 && ! $this->allows_daily_rentals && ! $this->price_per_hour) {
            throw ValidationException::withMessages(['price_per_hour' => 'Set an hourly rate or allow daily rentals.']);
        }
        if ($step === 4 && ! $this->pickup_available && ! $this->delivery_available) {
            throw ValidationException::withMessages(['pickup_available' => 'Select at least one option: pickup or delivery.']);
        }
    }

    protected function showValidationStep(ValidationException $exception): void
    {
        $errors = array_map(fn ($field) => explode('.', $field)[0], array_keys($exception->errors()));
        foreach (self::STEP_FIELDS as $step => $fields) {
            if (array_intersect($fields, $errors)) {
                $this->step = $step;
                break;
            }
        }
        $this->dispatch('listing-step-changed');
    }

    protected function advanceToStep(int $step): void
    {
        try {
            for ($previous = 1; $previous < $step; $previous++) {
                $this->validateStep($previous);
            }
        } catch (ValidationException $exception) {
            $this->showValidationStep($exception);
            throw $exception;
        }
        $this->resetValidation();
        $this->step = $step;
        $this->furthestStep = max($this->furthestStep, $step);
        $this->dispatch('listing-step-changed');
    }

    public function nextStep(): void
    {
        if ($this->step < 5) {
            $this->advanceToStep($this->step + 1);
        }
    }

    public function goToStep(int $step): void
    {
        if ($step < 1 || $step > $this->furthestStep) {
            return;
        }
        if ($step > $this->step) {
            $this->advanceToStep($step);

            return;
        }
        $this->resetValidation();
        $this->step = $step;
        $this->dispatch('listing-step-changed');
    }

    public function submitStep(): void
    {
        if ($this->step < 5) {
            $this->nextStep();
        } else {
            $this->save();
        }
    }

    public function save(): void
    {
        try {
            $this->persistListing();
        } catch (ValidationException $exception) {
            $this->showValidationStep($exception);
            throw $exception;
        }
    }

    protected function persistListing(): void
    {
        ListingPublication::ensureVerifiedOwner(auth()->id());
        $this->other_category = Str::squish($this->other_category);
        $this->other_subcategory = Str::squish($this->other_subcategory);
        if ($this->listing?->exists) {
            $this->listing = Listing::findOrFail($this->listing->id);
            $this->authorize('update', $this->listing);
        }

        $validated = $this->validate($this->publicationRules(), ListingPublication::messages());
        unset($validated['photos'], $validated['other_category'], $validated['other_subcategory']);

        if (! $this->pickup_available && ! $this->delivery_available) {
            $this->step = 4;
            $this->addError('pickup_available', 'Select at least one option: pickup or delivery.');

            return;
        }

        if (! $this->allows_daily_rentals && ! $this->price_per_hour) {
            $this->step = 3;
            $this->addError('price_per_hour', 'Set an hourly rate or allow daily rentals.');

            return;
        }

        $validated['owner_id'] = auth()->id();
        $validated['rejection_reason'] = null;

        $isUpdate = (bool) $this->listing?->exists;

        $storedPaths = [];
        $removedPaths = [];
        try {
            DB::transaction(function () use ($validated, $isUpdate, &$storedPaths, &$removedPaths) {
                ListingPublication::ensureVerifiedOwner(auth()->id());
                $listing = $isUpdate ? Listing::lockForUpdate()->findOrFail($this->listing->id) : new Listing;
                if ($isUpdate) {
                    $this->authorize('update', $listing);
                    if ($listing->reservingRequests()
                        ->where(fn ($query) => $query->whereDate('start_date', '<', $this->available_from)
                            ->orWhereDate('end_date', '>', $this->available_until))->exists()) {
                        throw ValidationException::withMessages([
                            'available_until' => 'Your availability window must include existing approved bookings.',
                        ]);
                    }
                }
                if ($listing->images()->whereNotIn('id', $this->removedImageIds)->count() + count($this->photos) < 1) {
                    throw ValidationException::withMessages(['photos' => 'Add at least one photo of your item.']);
                }
                if ($listing->images()->whereNotIn('id', $this->removedImageIds)->count() + count($this->photos) > 20) {
                    throw ValidationException::withMessages(['photos' => 'Keep up to 20 photos per listing.']);
                }
                if ($this->category_id === 0) {
                    $validated['category_id'] = ListingCategories::resolve($this->other_category)->id;
                }
                if ($this->subcategory_id === 0) {
                    $validated['subcategory_id'] = ListingCategories::resolve($this->other_subcategory, $validated['category_id'])->id;
                }
                $validated['status'] = $listing->status === ListingStatus::Inactive
                    ? ListingStatus::Inactive : ListingStatus::Published;
                $listing->fill($validated)->save();

                $removedPaths = $listing->images()->whereIn('id', $this->removedImageIds)->pluck('path')->all();
                $listing->images()->whereIn('id', $this->removedImageIds)->delete();
                $sortOrder = ($listing->images()->max('sort_order') ?? -1) + 1;
                foreach ($this->photos as $photo) {
                    $path = $photo->store('listings', 'public');
                    if (! $path) {
                        throw ValidationException::withMessages(['photos' => 'The photo could not be saved. Please try again.']);
                    }
                    $storedPaths[] = $path;
                    ListingImage::create([
                        'listing_id' => $listing->id,
                        'path' => $path,
                        'sort_order' => $sortOrder++,
                    ]);
                }
                ListingProgress::notify($listing, $isUpdate ? 'updated' : 'published');
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($storedPaths);
            throw $exception;
        }
        Storage::disk('public')->delete($removedPaths);

        session()->flash('status', $isUpdate
            ? 'Listing updated.'
            : 'Listing published.');

        $this->redirect(route('owner.listings.index'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.owner.listings.form', [
            'categories' => Category::whereNull('parent_id')->orderBy('name')->get(),
            'subcategories' => $this->subcategories(),
        ]);
    }
}
