<div>
    @unless ($modal)
        <x-page-header eyebrow="Renting" title="Request to rent" :subtitle="$listing->name" />
    @endunless
    <div x-data @if ($modal) x-on:rental-dates-selected.window="$wire.$set('rental_unit', $event.detail.rentalUnit ?? 'day', false); $wire.$set('start_date', $event.detail.startDate, false); $wire.$set('accept_terms', false, false); $wire.$set('end_date', $event.detail.endDate)" @endif class="mx-auto max-w-3xl px-4 py-6 sm:px-6 {{ $modal ? '' : 'lg:px-8 lg:py-8' }}">
    @unless ($modal)
        <a href="{{ route('listings.show', $listing) }}" wire:navigate class="flex items-center gap-1 text-sm font-medium text-slate-500 hover:text-slate-700">
            <x-icon name="chevron-down" class="h-3.5 w-3.5 rotate-90" /> Back to listing
        </a>
    @endunless

    <div class="flex items-start justify-between gap-4 {{ $modal ? '' : 'mt-3' }}">
        <div>
            @if ($modal)
                <h1 id="rental-request-title" class="text-xl font-semibold text-slate-900">Request to rent</h1>
            @else
                <h2 id="rental-request-title" class="text-xl font-semibold text-slate-900">Rental details</h2>
            @endif
            <p id="rental-request-description" class="mt-1 text-sm text-slate-500">{{ $listing->name }} &middot; ₱{{ number_format($listing->allows_daily_rentals ? $listing->price_per_day : $listing->price_per_hour, 2) }}/{{ $listing->allows_daily_rentals ? 'day' : 'hour' }}</p>
        </div>
        @if ($modal)
            <button type="button" x-on:click="$dispatch('close-rental-request')" aria-label="Close rental request" class="grid h-10 w-10 shrink-0 place-items-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus:ring-4 focus:ring-blue-100">
                <x-icon name="x-mark" class="h-5 w-5" aria-hidden="true" />
            </button>
        @endif
    </div>

    @php($reviewOwner = $listing->owner?->fresh())
    @php($ownerIdentityApproved = $reviewOwner?->hasVerifiedIdentity())
    <x-identity-review-notice :owner="$reviewOwner" owner-message="This owner has not completed ID verification. An administrator must approve their identity before this item can receive rental requests." id="rental-owner-verification-notice" />
    <form wire:submit="submit" class="mt-6 space-y-6">
        <x-input-error :messages="$errors->get('identity')" role="alert" />
        <x-input-error :messages="$errors->get('listing')" role="alert" />
        <x-input-error :messages="$errors->get('cart')" role="alert" />
        <x-input-error :messages="$errors->get('waitlist')" role="alert" />

        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-sm font-semibold text-slate-700">Rental period</h2>
            <div class="mt-4">
                <x-input-label required for="rental-unit" value="Rent by" />
                <select id="rental-unit" wire:model.live="rental_unit" class="mt-1 w-full rounded-lg border-slate-300">
                    @if ($listing->allows_daily_rentals)<option value="day">Day · ₱{{ number_format($listing->price_per_day, 2) }}/day</option>@endif
                    @if ($listing->price_per_hour)<option value="hour">Hour · ₱{{ number_format($listing->price_per_hour, 2) }}/hour</option>@endif
                </select>
                <x-input-error :messages="$errors->get('rental_unit')" class="mt-2" />
            </div>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label required for="start_date" value="Start date" />
                    <x-text-input wire:model.live="start_date" id="start_date" type="date" min="{{ max(now('Asia/Manila')->toDateString(), $listing->available_from?->toDateString() ?? '') }}" max="{{ $listing->available_until?->toDateString() }}" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('start_date')" class="mt-2" />
                </div>
                <div>
                    <x-input-label required for="end_date" value="End date" />
                    <x-text-input wire:model.live="end_date" id="end_date" type="date" min="{{ max($start_date, now('Asia/Manila')->toDateString(), $listing->available_from?->toDateString() ?? '') }}" max="{{ $this->latestEndDate() }}" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('end_date')" class="mt-2" />
                </div>
            </div>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div><x-input-label required for="start-time" value="Start time" /><x-text-input id="start-time" type="time" wire:model.live="start_time" step="60" class="mt-1 w-full" /><x-input-error :messages="$errors->get('start_time')" class="mt-2" /></div>
                    <div><x-input-label required for="end-time" value="Return time" /><x-text-input id="end-time" type="time" wire:model.live="end_time" step="60" class="mt-1 w-full" /><x-input-error :messages="$errors->get('end_time')" class="mt-2" /></div>
                </div>
                <p class="mt-2 text-xs text-slate-500">Philippine time · Available {{ $listing->available_time_from }}–{{ $listing->available_time_until }}. Choose a future start time, including today when available.</p>
                <p class="mt-2 text-xs text-slate-500">Maximum rental days: {{ $listing->max_rental_duration_days }}. {{ $rental_unit === 'hour' ? 'Hourly rentals use whole hours, capped at '.($listing->max_rental_duration_days * 24).' hours; they are charged at the hourly rate.' : 'Both selected dates count toward daily billing.' }}</p>
            @if ($listing->available_from && $listing->available_until)
                <p class="mt-2 text-xs text-slate-500">Available {{ $listing->available_from->format('M d, Y') }} – {{ $listing->available_until->format('M d, Y') }}.</p>
            @endif

            @if ($listing->pickup_available && $listing->delivery_available)
                <div class="mt-4">
                    <x-input-label required value="Fulfillment" />
                    <div class="mt-2 flex gap-4">
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="radio" wire:model.live="fulfillment_method" value="pickup" class="text-blue-600 focus:ring-blue-500">
                            Pickup
                        </label>
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="radio" wire:model.live="fulfillment_method" value="delivery" class="text-blue-600 focus:ring-blue-500">
                            Delivery
                        </label>
                    </div>
                </div>
            @else
                <p class="mt-4 text-sm text-slate-600">
                    Fulfillment: <span class="font-medium">{{ $listing->pickup_available ? 'Pickup' : 'Delivery' }}</span> (only option offered by the owner)
                </p>
            @endif
            <x-input-error :messages="$errors->get('fulfillment_method')" class="mt-2" />
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-sm font-semibold text-slate-700">Cost breakdown</h2>
            <p class="mt-2 text-xs text-slate-500">Processing fee is charged once on the rental fee. Cancellation fees are not included; they apply only if the rental is cancelled under the saved policy.</p>
            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-slate-500">Rental fee ({{ $rental_unit === 'hour' ? $this->rentalHours().' hours' : $this->rentalDays().' days' }} &times; ₱{{ number_format($rental_unit === 'hour' ? $listing->price_per_hour : $listing->price_per_day, 2) }})</dt>
                    <dd class="font-medium text-slate-800">₱{{ number_format($this->rentalFee(), 2) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500">Processing fee ( {{ rtrim(rtrim(number_format($this->commissionRate(), 2), '0'), '.') }}%)</dt>
                    <dd class="font-medium text-slate-800">₱{{ number_format($this->commissionAmount(), 2) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500">Security deposit (refundable)</dt>
                    <dd class="font-medium text-slate-800">₱{{ number_format($listing->security_deposit, 2) }}</dd>
                </div>
                <div class="flex justify-between border-t border-slate-100 pt-2 text-base">
                    <dt class="font-semibold text-slate-900">Total due</dt>
                    <dd class="font-semibold text-slate-900">₱{{ number_format($this->totalAmount(), 2) }}</dd>
                </div>
            </dl>
        </section>

        <p class="text-sm text-slate-600">Save these dates to your cart or send a request directly. Adding to your cart does not reserve the item or notify the owner. If the owner approves a request, both of you will review and accept the rental agreement before the booking is finalized.</p>

        <div>
            <label for="accept_terms" class="flex items-start gap-3 text-sm text-slate-700">
                <input id="accept_terms" type="checkbox" wire:model="accept_terms" class="mt-1 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                <span>I have reviewed the rental period, selected rate, processing fee and security deposit shown above.</span>
            <x-required-mark /></label>
            <x-input-error :messages="$errors->get('accept_terms')" class="mt-2" />
        </div>

        <div class="flex flex-wrap justify-end gap-3">
            @if ($modal)
                <x-secondary-button x-on:click="$dispatch('close-rental-request')">Cancel</x-secondary-button>
            @endif
            <x-secondary-button type="button" wire:click="addToCart" wire:loading.attr="disabled" wire:target="addToCart,submit" class="disabled:cursor-wait disabled:opacity-60">Add to cart</x-secondary-button>
            @if ($canJoinWaitlist)
                <x-secondary-button type="button" wire:click="joinWaitlist" wire:loading.attr="disabled" wire:target="joinWaitlist">Join waitlist for these dates</x-secondary-button>
            @endif
            <x-primary-button :disabled="! auth()->user()?->fresh()?->hasVerifiedIdentity() || ! $ownerIdentityApproved" :aria-describedby="$ownerIdentityApproved ? null : 'rental-owner-verification-notice'" wire:loading.attr="disabled" wire:loading.class="cursor-wait" wire:loading.class.remove="disabled:cursor-not-allowed" wire:target="submit,addToCart" class="disabled:cursor-not-allowed disabled:opacity-60">Send rental request</x-primary-button>
        </div>
    </form>
    </div>
</div>
