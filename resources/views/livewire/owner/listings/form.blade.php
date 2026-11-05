@php
    $steps = [
        1 => ['title' => 'Item Details', 'short' => 'Details', 'description' => 'Start with the basics. Tell renters what you are sharing.'],
        2 => ['title' => 'Photos', 'short' => 'Photos', 'description' => 'Give renters a clear look at your item from a few different angles.'],
        3 => ['title' => 'Pricing & Availability', 'short' => 'Rental Terms', 'description' => 'Choose your rates and when your item can be rented.'],
        4 => ['title' => 'Location & Handover', 'short' => 'Location', 'description' => 'Set your location, handover options, and any rental rules.'],
        5 => ['title' => 'Review Your Listing', 'short' => 'Review', 'description' => 'Take one last look. You can go back and edit any section before publishing.'],
    ];
@endphp
<div class="listing-form-page bg-slate-50">
    <x-page-header eyebrow="Owning" :title="$listing?->exists ? 'Edit Listing' : 'Create a Listing'" subtitle="A few simple steps to get your item ready for renters." />
    <x-identity-review-notice />
    <form wire:submit="submitStep" novalidate
        x-data="{ photoUploading: false }"
        x-on:livewire-upload-start="photoUploading = true"
        x-on:livewire-upload-finish="photoUploading = false"
        x-on:livewire-upload-error="photoUploading = false"
        x-on:livewire-upload-cancel="photoUploading = false"
        x-on:listing-step-changed.window="$nextTick(() => { $refs.progress.scrollIntoView({ block: 'start', behavior: 'instant' }); $refs.stepHeading.focus({ preventScroll: true }); })"
        class="listing-form-content mx-auto w-full max-w-6xl px-4 py-5 sm:px-6 lg:px-8 lg:py-7">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-2">
            <a href="{{ route('owner.listings.index') }}" wire:navigate class="inline-flex min-h-10 items-center gap-2 text-sm font-semibold text-slate-500 transition hover:text-blue-700">
                <span aria-hidden="true">&larr;</span> My Listings
            </a>
            <span class="text-xs text-slate-500">Fields marked <span class="font-semibold text-rose-500">*</span> are required</span>
        </div>
        <nav x-ref="progress" class="listing-steps" aria-label="Listing progress">
            <ol class="grid grid-cols-5">
                @foreach ($steps as $number => $details)
                    <li class="listing-step {{ $step === $number ? 'is-current' : '' }} {{ $number < $furthestStep && $step !== $number ? 'is-complete' : '' }}">
                        <button type="button" wire:click="goToStep({{ $number }})" @disabled($number > $furthestStep)
                            @if ($step === $number) aria-current="step" @endif
                            aria-label="Step {{ $number }}: {{ $details['title'] }}"
                            wire:loading.attr="disabled"
                            class="listing-step__button">
                            <span class="listing-step__number" aria-hidden="true">
                                @if ($number < $furthestStep && $step !== $number)
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" /></svg>
                                @else
                                    {{ $number }}
                                @endif
                            </span>
                            <span class="listing-step__label"><span class="hidden md:inline">{{ $details['title'] }}</span><span class="md:hidden">{{ $details['short'] }}</span></span>
                        </button>
                    </li>
                @endforeach
            </ol>
        </nav>
        <div class="listing-step-heading mb-5 mt-7 flex items-start justify-between gap-4">
            <div>
                <p class="mb-1 text-xs font-bold uppercase tracking-widest text-blue-600">Step {{ $step }} of 5</p>
                <h2 x-ref="stepHeading" id="listing-step-title" tabindex="-1" class="text-xl font-bold tracking-tight text-[#071a3d] outline-none sm:text-2xl">{{ $steps[$step]['title'] }}</h2>
                <p class="mt-1.5 text-sm leading-6 text-slate-500">{{ $steps[$step]['description'] }}</p>
            </div>
        </div>
        <x-input-error :messages="$errors->get('identity')" class="mb-4" role="alert" />
        @if ($listing?->status?->value === 'rejected' && $listing->rejection_reason)
            <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
                <p class="font-semibold">This listing was rejected</p><p class="mt-1">{{ $listing->rejection_reason }}</p>
            </div>
        @endif
        @if ($errors->any())
            <div role="alert" class="mb-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
                <p class="font-semibold">Please check the highlighted fields to continue.</p>
                <ul class="mt-2 list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif
        <div style="{{ $step === 1 ? '' : 'display: none;' }}" class="listing-form-grid listing-form-grid--details" role="region" aria-labelledby="listing-step-title" wire:key="listing-step-1">
<section class="listing-form-card rounded-2xl border border-slate-200/80 bg-white p-5 sm:p-6">
                        <div class="flex items-center gap-3">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-blue-50 text-blue-600 ring-1 ring-blue-100"><x-icon name="folder" class="h-5 w-5" /></span>
                            <div><h2 class="text-base font-bold text-[#071a3d]">Category</h2><p class="mt-0.5 text-xs leading-5 text-slate-500">Help renters find your item quickly.</p></div>
                        </div>
                        <div class="mt-4 grid grid-cols-1 gap-4 min-[900px]:grid-cols-1">
                            <div>
                                <x-input-label required for="category_id" value="Category" />
                                <select wire:model.live="category_id" id="category_id" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">Select a category</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                    <option value="0">Other</option>
                                </select>
                                <x-input-error :messages="$errors->get('category_id')" class="mt-2" />
                                @if ($category_id === 0)
                                    <x-input-label required for="other_category" value="Specify category" class="mt-3" />
                                    <x-text-input wire:model="other_category" id="other_category" required maxlength="100" class="mt-1 block w-full" />
                                    <x-input-error :messages="$errors->get('other_category')" class="mt-2" />
                                @endif
                            </div>
                            <div>
                                <x-input-label for="subcategory_id" value="Subcategory (optional)" />
                                <select wire:model.live="subcategory_id" id="subcategory_id" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" @if ($category_id === null) disabled @endif>
                                    <option value="">None</option>
                                    @foreach ($subcategories as $subcategory)
                                        <option value="{{ $subcategory->id }}">{{ $subcategory->name }}</option>
                                    @endforeach
                                    <option value="0">Other</option>
                                </select>
                                <x-input-error :messages="$errors->get('subcategory_id')" class="mt-2" />
                                @if ($subcategory_id === 0)
                                    <x-input-label required for="other_subcategory" value="Specify subcategory" class="mt-3" />
                                    <x-text-input wire:model="other_subcategory" id="other_subcategory" required maxlength="100" class="mt-1 block w-full" />
                                    <x-input-error :messages="$errors->get('other_subcategory')" class="mt-2" />
                                @endif
                            </div>
                        </div>
                    </section>
<section class="listing-form-card rounded-2xl border border-slate-200/80 bg-white p-5 sm:p-6">
                        <div class="flex items-center gap-3">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-sky-50 text-sky-600 ring-1 ring-sky-100"><x-icon name="tag" class="h-5 w-5" /></span>
                            <div><h2 class="text-base font-bold text-[#071a3d]">Item details</h2><p class="mt-0.5 text-xs leading-5 text-slate-500">Describe what makes your item useful.</p></div>
                        </div>
                        <div class="mt-4 space-y-4">
                            <div>
                                <x-input-label required for="name" value="Item name" />
                                <x-text-input wire:model="name" id="name" type="text" class="mt-1 block w-full" />
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <x-input-label for="brand" value="Brand (optional)" />
                                    <x-text-input wire:model="brand" id="brand" type="text" class="mt-1 block w-full" />
                                    <x-input-error :messages="$errors->get('brand')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="model" value="Model (optional)" />
                                    <x-text-input wire:model="model" id="model" type="text" class="mt-1 block w-full" />
                                    <x-input-error :messages="$errors->get('model')" class="mt-2" />
                                </div>
                            </div>

                            <div>
                                <x-input-label required for="description" value="Description" />
                                <textarea wire:model="description" id="description" rows="4" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                                <x-input-error :messages="$errors->get('description')" class="mt-2" />
                            </div>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <x-input-label required for="condition" value="Condition" />
                                    <select wire:model="condition" id="condition" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        @foreach (\App\Enums\ListingCondition::cases() as $option)
                                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('condition')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="purchase_year" value="Purchase year (optional)" />
                                    <x-text-input wire:model="purchase_year" id="purchase_year" type="number" class="mt-1 block w-full" />
                                    <x-input-error :messages="$errors->get('purchase_year')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="estimated_original_price" value="Original price (₱, optional)" />
                                    <x-text-input wire:model.live.debounce.300ms="estimated_original_price" id="estimated_original_price" type="number" min="0" step="0.01" class="mt-1 block w-full" />
                                    <x-input-error :messages="$errors->get('estimated_original_price')" class="mt-2" />
                                </div>
                            </div>
                            @php $insight = $this->marketInsight(); @endphp
                            <x-listing-market-insight :insight="$insight" :original-price="$estimated_original_price" :high-price="$this->isPriceSignificantlyHigh()" />
                        </div>
                    </section>
        </div>
        <div style="{{ $step === 2 ? '' : 'display: none;' }}" class="space-y-5" role="region" aria-labelledby="listing-step-title" wire:key="listing-step-2">
<section class="listing-form-card rounded-2xl border border-slate-200/80 bg-white p-5 sm:p-6">
                        <div class="flex items-center gap-3">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-violet-50 text-violet-600 ring-1 ring-violet-100"><x-icon name="camera" class="h-5 w-5" /></span>
                            <div><h2 class="text-base font-bold text-[#071a3d]">Photos</h2><p class="mt-0.5 text-xs leading-5 text-slate-500">Show clear angles and important details.</p></div>
                        </div>

                        @if (($this->existingPhotoCount() + count($photos)) < 2)
                            <div class="mt-3 rounded-xl bg-slate-50 p-3 text-sm text-slate-600">
                                We recommend adding at least 2 photos — listings with more photos get more rental requests.
                            </div>
                        @endif

                        @if ($listing?->exists && $listing->images->isNotEmpty())
                            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                                @foreach ($listing->images->whereNotIn('id', $removedImageIds) as $image)
                                    <div class="group relative overflow-hidden rounded-lg border border-slate-200" wire:key="existing-{{ $image->id }}">
                                        <img src="{{ $image->url() }}" alt="{{ $listing->name }} photo {{ $loop->iteration }}" class="aspect-[4/3] w-full object-cover">
                                        <button type="button" wire:click="removeExistingImage({{ $image->id }})" wire:confirm="Remove this photo?" aria-label="Remove photo {{ $loop->iteration }}" class="absolute right-2 top-2 grid h-9 w-9 place-items-center rounded-full bg-white text-lg font-semibold text-rose-600 shadow transition hover:bg-rose-50 focus:outline-none focus:ring-4 focus:ring-rose-100">
                                            &times;
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <div class="mt-4 rounded-2xl border-2 border-dashed border-blue-200 bg-blue-50/50 p-6 sm:p-8 transition hover:border-blue-300 hover:bg-blue-50">
                            <label for="listing-photos" class="mb-2 block text-sm font-semibold text-slate-700">Add photos @if ($this->existingPhotoCount() === 0)<x-required-mark />@endif</label>
                            <p id="listing-photos-help" class="mb-3 text-xs leading-5 text-slate-500">Add 1–20 photos, up to 4 MB each. Select more anytime; earlier photos stay below.</p>
                            <p class="mb-3 text-sm font-semibold text-slate-700" role="status">{{ $this->existingPhotoCount() + count($photos) }} / 20 photos selected</p>
                            <input id="listing-photos" aria-describedby="listing-photos-help" type="file" wire:model="photoBatch" multiple accept="image/*" :disabled="photoUploading" class="block w-full text-sm text-slate-600 file:mr-4 file:rounded-xl file:border-0 file:bg-blue-600 file:px-4 file:py-2.5 file:text-sm file:font-bold file:text-white hover:file:bg-blue-700">
                            <div wire:loading wire:target="photoBatch" class="mt-2 text-xs leading-5 text-slate-500">Uploading...</div>
                            <x-input-error :messages="collect($errors->get('photoBatch.*'))->flatten()->all()" class="mt-2" />
                            <x-input-error :messages="$errors->get('photoBatch')" class="mt-2" />
                            <x-input-error :messages="collect($errors->get('photos.*'))->flatten()->all()" class="mt-2" />
                            <x-input-error :messages="$errors->get('photos')" class="mt-2" />
                        </div>

                        @if (count($photos) > 0)
                            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                                @foreach ($photos as $index => $photo)
                                    <div class="group relative overflow-hidden rounded-lg border border-slate-200" wire:key="pending-{{ $photo->getFilename() }}">
                                        @if ($photo->isPreviewable())
                                            <img src="{{ $photo->temporaryUrl() }}" alt="New listing photo {{ $loop->iteration }}" class="aspect-[4/3] w-full object-cover">
                                        @else
                                            <p class="p-4 text-sm text-rose-700">Choose an image file for this photo.</p>
                                        @endif
                                        <button type="button" wire:click="removePendingPhoto({{ $index }})" aria-label="Remove photo {{ $loop->iteration }}" class="absolute right-2 top-2 grid h-9 w-9 place-items-center rounded-full bg-white text-lg font-semibold text-rose-600 shadow transition hover:bg-rose-50 focus:outline-none focus:ring-4 focus:ring-rose-100">
                                            &times;
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </section>
        </div>
        <div style="{{ $step === 3 ? '' : 'display: none;' }}" class="listing-form-grid" role="region" aria-labelledby="listing-step-title" wire:key="listing-step-3">
<section class="listing-form-card rounded-2xl border border-slate-200/80 bg-white p-5 sm:p-6">
                        <div class="flex items-center gap-3">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-indigo-50 text-indigo-600 ring-1 ring-indigo-100"><span class="text-base font-extrabold">₱</span></span>
                            <div><h2 class="text-base font-bold text-[#071a3d]">Pricing & deposit</h2><p class="mt-0.5 text-xs leading-5 text-slate-500">Set fair rates and protect your item.</p></div>
                        </div>
                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label required for="price_per_day" value="Price per day (₱)" />
                                <x-text-input wire:model.live.debounce.500ms="price_per_day" id="price_per_day" type="number" step="0.01" class="mt-1 block w-full" />
                                <x-input-error :messages="$errors->get('price_per_day')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label required for="security_deposit" value="Security deposit (₱)" />
                                <x-text-input wire:model="security_deposit" id="security_deposit" type="number" step="0.01" class="mt-1 block w-full" />
                                <x-input-error :messages="$errors->get('security_deposit')" class="mt-2" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label for="price_per_hour" value="Price per hour (optional — enables hourly rental)" />
                                <x-text-input wire:model="price_per_hour" id="price_per_hour" type="number" step="0.01" class="mt-1 block w-full" />
                                <x-input-error :messages="$errors->get('price_per_hour')" class="mt-2" />
                                <label class="mt-3 flex items-center gap-2 text-sm"><input type="checkbox" wire:model="allows_daily_rentals" class="rounded border-slate-300">Also allow daily rentals</label>
                                <x-input-error :messages="$errors->get('allows_daily_rentals')" />
                                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                    <div><x-input-label required for="available-time-from" value="Available from (Philippine time)" /><x-text-input id="available-time-from" type="time" wire:model="available_time_from" class="mt-1 w-full" /><x-input-error :messages="$errors->get('available_time_from')" /></div>
                                    <div><x-input-label required for="available-time-until" value="Available until (Philippine time)" /><x-text-input id="available-time-until" type="time" wire:model="available_time_until" class="mt-1 w-full" /><x-input-error :messages="$errors->get('available_time_until')" /></div>
                                </div>
                            </div>
                            <div>
                                <x-input-label for="price_per_week" value="Price per week (optional)" />
                                <x-text-input wire:model="price_per_week" id="price_per_week" type="number" step="0.01" class="mt-1 block w-full" />
                                <x-input-error :messages="$errors->get('price_per_week')" class="mt-2" />
                            </div>
                        </div>


                    @php $insight = $this->marketInsight(); @endphp
<x-listing-market-insight :insight="$insight" :original-price="$estimated_original_price" :high-price="$this->isPriceSignificantlyHigh()" />
</section>
<section class="listing-form-card rounded-2xl border border-slate-200/80 bg-white p-5 sm:p-6">
        <div class="flex items-center gap-3">
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-blue-50 text-blue-600 ring-1 ring-blue-100"><x-icon name="clipboard-list" class="h-5 w-5" /></span>
            <div><h3 class="text-base font-bold text-[#071a3d]">Schedule & Availability</h3><p class="mt-0.5 text-xs leading-5 text-slate-500">Set the dates, hours, and length of each rental.</p></div>
        </div>
        <div class="mt-4 space-y-4">                            <div>
                                <x-input-label required for="max_rental_duration_days" value="Maximum rental days" />
                                <x-text-input wire:model="max_rental_duration_days" id="max_rental_duration_days" type="number" required min="1" max="365" class="mt-1 block w-full sm:w-48" />
                                <x-input-error :messages="$errors->get('max_rental_duration_days')" class="mt-2" />
                            </div>

                            <div>
                                <p class="text-sm font-medium text-slate-700">Available dates</p>
                                <p class="mt-1 text-xs text-slate-500">Renters must start and finish their rental within this window.</p>
                                <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <x-input-label required for="available_from" value="Available from" />
                                        <x-text-input wire:model="available_from" id="available_from" type="date" required class="mt-1 block w-full" />
                                        <x-input-error :messages="$errors->get('available_from')" class="mt-2" />
                                    </div>
                                    <div>
                                        <x-input-label required for="available_until" value="Available until" />
                                        <x-text-input wire:model="available_until" id="available_until" type="date" required min="{{ now()->toDateString() }}" class="mt-1 block w-full" />
                                        <x-input-error :messages="$errors->get('available_until')" class="mt-2" />
                                    </div>
                                </div>
                            </div>

                            <label class="flex items-center gap-2 text-sm text-slate-700">
                                <input type="checkbox" wire:model="is_available" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                This item is currently available for rent
                            </label>
                            @if ($listing?->reopen_required)
                                <p class="text-xs leading-5 text-amber-700">This item needs inspection. Reopen it from My Listings after return; saving these details keeps that requirement.</p>
                            @endif
</div>
    </section>
        </div>
        <div style="{{ $step === 4 ? '' : 'display: none;' }}" class="listing-form-grid listing-form-grid--location" role="region" aria-labelledby="listing-step-title" wire:key="listing-step-4">
<section class="listing-form-card rounded-2xl border border-slate-200/80 bg-white p-5 sm:p-6">
                        <div class="flex items-center gap-3">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-emerald-50 text-emerald-600 ring-1 ring-emerald-100"><x-icon name="map-pin" class="h-5 w-5" /></span>
                            <div><h2 class="text-base font-bold text-[#071a3d]">Item location</h2><p class="mt-0.5 text-xs leading-5 text-slate-500">Help renters see where your item is located.</p></div>
                        </div>
                        <div class="mt-4 space-y-4">
                            <div>
                                <x-input-label required for="location" value="Location" />
                                <x-text-input wire:model="location" id="location" type="text" placeholder="e.g. Nasugbu, Batangas" class="mt-1 block w-full" />
                                <x-input-error :messages="$errors->get('location')" class="mt-2" />
                            </div>

                            <div wire:ignore x-data="locationPicker(@js($latitude), @js($longitude))"
                                x-on:listing-step-changed.window="$nextTick(() => { if ($wire.step === 4) map.invalidateSize({ pan: false }); })">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <x-input-label required value="Map pin (required)" />
                                    <button type="button" @click="useMyLocation" :disabled="locating" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-700 hover:border-blue-300 hover:bg-blue-100 focus:outline-none focus:ring-4 focus:ring-blue-100 disabled:opacity-60">
                                        <x-icon name="map-pin" class="h-4 w-4 shrink-0" aria-hidden="true" />
                                        <span x-text="locating ? 'Finding location…' : 'Use current location'">Use current location</span>
                                    </button>
                                </div>
                                <div x-ref="map" class="relative isolate z-0 mt-2 h-64 w-full overflow-hidden rounded-2xl border border-slate-200 shadow-sm"></div>
                                <p class="mt-1 text-xs leading-5 text-slate-500">Click or drag the pin to fill the address. Check it and add pickup details as needed.</p>
                                <p class="mt-1 text-xs text-slate-500">Address lookup sends pin coordinates to Photon. Address data © OpenStreetMap contributors.</p>
                                <p x-cloak x-show="addressLoading" role="status" class="mt-2 text-xs text-blue-700">Finding address…</p>
                                <p x-cloak x-show="!addressLoading && locationStatus" x-text="locationStatus" role="status" class="mt-2 text-xs text-slate-600"></p>
                                <p x-cloak x-show="locationError" x-text="locationError" role="alert" class="mt-2 text-xs text-amber-700"></p>
                                <button type="button" x-cloak x-show="marker && !addressLoading" @click="retryAddress" class="mt-2 text-xs font-semibold text-blue-700 underline">Look up this pin again</button>
                            </div>
                            <x-input-error :messages="$errors->get('latitude')" class="mt-2" />
                            <x-input-error :messages="$errors->get('longitude')" class="mt-2" />

                        </div>
                    </section>
<section class="listing-form-card rounded-2xl border border-slate-200/80 bg-white p-5 sm:p-6">
        <div class="flex items-center gap-3">
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-blue-50 text-blue-600 ring-1 ring-blue-100"><x-icon name="list" class="h-5 w-5" /></span>
            <div><h3 class="text-base font-bold text-[#071a3d]">Handover & Rules</h3><p class="mt-0.5 text-xs leading-5 text-slate-500">Choose how renters receive and care for your item.</p></div>
        </div>
        <div class="mt-4 space-y-4">                            <div>
                                <x-input-label required value="Fulfillment options" />
                                <div class="mt-2 grid gap-3 sm:grid-cols-2">
                                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-slate-50/70 p-3 text-sm font-medium text-slate-700 transition hover:border-blue-200 hover:bg-blue-50">
                                        <input type="checkbox" wire:model="pickup_available" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                        Pickup available
                                    </label>
                                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-slate-50/70 p-3 text-sm font-medium text-slate-700 transition hover:border-blue-200 hover:bg-blue-50">
                                        <input type="checkbox" wire:model="delivery_available" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                        Delivery available
                                    </label>
                                </div>
                                <x-input-error :messages="$errors->get('pickup_available')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="rental_rules" value="Rental rules (optional)" />
                                <textarea wire:model="rental_rules" id="rental_rules" rows="3" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="e.g. Must be returned clean. No outdoor use in rain."></textarea>
                                <x-input-error :messages="$errors->get('rental_rules')" class="mt-2" />
                            </div>

</div>
    </section>
        </div>
        @if ($step === 5)
        <div class="space-y-5" role="region" aria-labelledby="listing-step-title">
            <div class="rounded-2xl border border-blue-100 bg-blue-50 px-5 py-4 text-sm leading-6 text-blue-800">
                {{ $listing?->exists ? 'Your changes will be applied when you save. Paused listings will stay paused.' : 'Your listing will be visible to renters as soon as you publish it.' }}
            </div>
            <div class="listing-form-grid">
                <section class="listing-form-card rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                    <div class="mb-4 flex items-center justify-between gap-3"><h3 class="font-bold text-[#071a3d]">Item Details & Photos</h3><button type="button" wire:click="goToStep(1)" class="listing-review-edit">Edit Details</button></div>
                    <p class="text-lg font-bold text-slate-900 break-words">{{ $name }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ $category_id === 0 ? $other_category : $categories->firstWhere('id', $category_id)?->name }}@if ($subcategory_id !== null) &middot; {{ $subcategory_id === 0 ? $other_subcategory : $subcategories->firstWhere('id', $subcategory_id)?->name }}@endif</p>
                    <p class="mt-3 whitespace-pre-line break-words text-sm leading-6 text-slate-600">{{ $description }}</p>
                    <dl class="listing-review-details mt-4">
                        <div><dt>Condition</dt><dd>{{ \App\Enums\ListingCondition::from($condition)->label() }}</dd></div>
                        @if ($brand)<div><dt>Brand</dt><dd>{{ $brand }}</dd></div>@endif
                        @if ($model)<div><dt>Model</dt><dd>{{ $model }}</dd></div>@endif
                        @if ($purchase_year)<div><dt>Purchase Year</dt><dd>{{ $purchase_year }}</dd></div>@endif
                        @if ($estimated_original_price !== null)<div><dt>Original Price</dt><dd>&#8369;{{ number_format($estimated_original_price, 2) }}</dd></div>@endif
                    </dl>
                    <div class="mb-3 mt-5 flex items-center justify-between"><p class="text-sm font-semibold text-slate-700">{{ $this->existingPhotoCount() + count($photos) }} Photos</p><button type="button" wire:click="goToStep(2)" class="listing-review-edit">Edit Photos</button></div>
                    <div class="grid grid-cols-3 gap-2">
                        @foreach (($listing?->images ?? collect())->whereNotIn('id', $removedImageIds) as $image)
                            <img src="{{ $image->url() }}" alt="{{ $name }} photo {{ $loop->iteration }}" class="aspect-[4/3] w-full rounded-lg object-cover" />
                        @endforeach
                        @foreach ($photos as $photo)
                            @if ($photo->isPreviewable())<img src="{{ $photo->temporaryUrl() }}" alt="{{ $name }} uploaded photo {{ $loop->iteration }}" class="aspect-[4/3] w-full rounded-lg object-cover" />@endif
                        @endforeach
                    </div>
                </section>
                <div class="space-y-5">
                    <section class="listing-form-card rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                        <div class="mb-4 flex items-center justify-between gap-3"><h3 class="font-bold text-[#071a3d]">Pricing & Availability</h3><button type="button" wire:click="goToStep(3)" class="listing-review-edit">Edit Rates</button></div>
                        <dl class="listing-review-details">
                            <div><dt>Daily Rate</dt><dd>&#8369;{{ number_format($price_per_day, 2) }}{{ $allows_daily_rentals ? '' : ' (daily rentals disabled)' }}</dd></div>
                            @if ($price_per_hour)<div><dt>Hourly Rate</dt><dd>&#8369;{{ number_format($price_per_hour, 2) }}</dd></div>@endif
                            @if ($price_per_week)<div><dt>Weekly Rate</dt><dd>&#8369;{{ number_format($price_per_week, 2) }}</dd></div>@endif
                            <div><dt>Security Deposit</dt><dd>&#8369;{{ number_format($security_deposit, 2) }}</dd></div>
                            <div><dt>Available Dates</dt><dd>{{ \Carbon\Carbon::parse($available_from)->format('M d, Y') }} &ndash; {{ \Carbon\Carbon::parse($available_until)->format('M d, Y') }}</dd></div>
                            <div><dt>Available Hours</dt><dd>{{ \Carbon\Carbon::parse($available_time_from)->format('g:i A') }} &ndash; {{ \Carbon\Carbon::parse($available_time_until)->format('g:i A') }} (PH)</dd></div>
                            <div><dt>Maximum Rental</dt><dd>{{ $max_rental_duration_days }} {{ \Illuminate\Support\Str::plural('day', $max_rental_duration_days) }}</dd></div>
                            <div><dt>Availability</dt><dd>{{ $is_available ? 'Available for rent' : 'Currently unavailable' }}</dd></div>
                        </dl>
                    </section>
                    <section class="listing-form-card rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                        <div class="mb-4 flex items-center justify-between gap-3"><h3 class="font-bold text-[#071a3d]">Location & Handover</h3><button type="button" wire:click="goToStep(4)" class="listing-review-edit">Edit Location</button></div>
                        <dl class="listing-review-details">
                            <div><dt>Location</dt><dd>{{ $location }}</dd></div>
                            <div><dt>Map Pin</dt><dd>{{ $latitude }}, {{ $longitude }}</dd></div>
                            <div><dt>Handover</dt><dd>{{ collect([$pickup_available ? 'Pickup' : null, $delivery_available ? 'Delivery' : null])->filter()->join(' & ') }}</dd></div>
                            <div><dt>Rental Rules</dt><dd class="whitespace-pre-line">{{ $rental_rules ?: 'No additional rules.' }}</dd></div>
                        </dl>
                    </section>
                </div>
            </div>
        </div>
        @endif
        <footer class="listing-form-actions mt-6 flex flex-col gap-4 border-t border-slate-200 pt-5 sm:flex-row sm:items-center sm:justify-between">
            <a href="{{ route('owner.listings.index') }}" wire:navigate class="text-center text-sm font-semibold text-slate-500 hover:text-slate-800">Cancel</a>
            <div class="flex items-center justify-end gap-3">
                @if ($step > 1)
                    <button type="button" wire:click="goToStep({{ $step - 1 }})" wire:loading.attr="disabled" :disabled="photoUploading" class="inline-flex min-h-11 flex-1 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 disabled:opacity-50 sm:flex-none"><span aria-hidden="true">&larr;</span> Back</button>
                @endif
                <button type="submit" :disabled="photoUploading" wire:loading.attr="disabled" class="inline-flex min-h-11 flex-1 items-center justify-center gap-2 rounded-xl bg-[#075cf5] px-6 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-100 disabled:cursor-wait disabled:opacity-60 sm:flex-none">
                    <span wire:loading.remove wire:target="submitStep,nextStep,goToStep,save">{{ $step === 5 ? ($listing?->exists ? 'Save Changes' : 'Publish Listing') : ($step === 4 ? 'Review Listing' : 'Continue') }}</span>
                    <span wire:loading wire:target="submitStep,nextStep,goToStep,save">{{ $step === 5 ? 'Saving...' : 'Checking...' }}</span>
                    @if ($step < 5)<span wire:loading.remove wire:target="submitStep,nextStep,goToStep,save" aria-hidden="true">&rarr;</span>@endif
                </button>
            </div>
        </footer>
    </form>
</div>
