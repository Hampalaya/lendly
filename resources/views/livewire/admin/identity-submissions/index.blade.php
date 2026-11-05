<div>
    <x-page-header eyebrow="Administration" title="ID reviews" subtitle="Review National ID photos and selfies before enabling renting and listing." />
    <div class="px-4 pt-3 sm:px-6 lg:px-8">
        <x-record-toolbar :filter="$filter" :sort="$sort" :filters="['all' => 'All Statuses', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled']" placeholder="Search ID reviews..." search-label="Search ID reviews" label="ID review controls" />
    </div>

    <div class="w-full space-y-3 px-4 pb-6 pt-3 sm:px-6 lg:px-8">
        @if (session('status'))
            <p class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800" role="status">{{ session('status') }}</p>
        @endif

        <div class="grid items-start gap-6 xl:grid-cols-[20rem_minmax(0,1fr)]">
            <section aria-label="ID review queue" class="space-y-3">
                @forelse ($submissions as $item)
                    <button type="button" wire:click="selectSubmission({{ $item->id }})" wire:key="submission-{{ $item->id }}" aria-pressed="{{ $selectedId === $item->id ? 'true' : 'false' }}" class="block w-full rounded-xl border bg-white p-4 text-left shadow-sm focus:outline-none focus:ring-4 focus:ring-blue-100 {{ $selectedId === $item->id ? 'border-blue-500 ring-1 ring-blue-500' : 'border-slate-200 hover:border-blue-300' }}">
                        <span class="flex flex-wrap items-center justify-between gap-2">
                            <span class="font-semibold text-slate-900">{{ $item->user?->name ?? 'Unavailable account' }}</span>
                            <x-badge :color="['pending' => 'blue', 'approved' => 'green', 'rejected' => 'red', 'cancelled' => 'gray'][$item->status] ?? 'gray'">{{ ucfirst($item->status) }}</x-badge>
                        </span>
                        <span class="mt-2 block text-xs text-slate-500">#{{ $item->id }} · {{ $item->created_at->format('M j, Y g:i A') }}</span>
                    </button>
                @empty
                    <x-empty-state title="No ID reviews" message="No submissions match this filter." />
                @endforelse
                {{ $submissions->links() }}
            </section>

            @if ($selected)
                <section wire:key="selected-submission-{{ $selected->id }}" class="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="selected-review-title">
                    <div class="border-b border-slate-100 p-5 sm:p-6">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 id="selected-review-title" class="text-xl font-bold text-slate-900">{{ $selected->user?->name ?? 'Unavailable account' }}</h2>
                                <p class="mt-1 break-all text-sm text-slate-500">{{ $selected->user?->email }}</p>
                                <p class="mt-2 text-xs text-slate-500">Submission #{{ $selected->id }} · {{ $selected->created_at->format('M j, Y g:i A') }}</p>
                            </div>
                            <x-badge :color="['pending' => 'blue', 'approved' => 'green', 'rejected' => 'red', 'cancelled' => 'gray'][$selected->status] ?? 'gray'">{{ ucfirst($selected->status) }}</x-badge>
                        </div>
                    </div>

                    <div class="space-y-6 p-5 sm:p-6">
                        @if ($errors->any())
                            <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
                                <p class="font-semibold">The review could not be saved.</p>
                                <ul class="mt-2 list-inside list-disc space-y-1">@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
                            </div>
                        @endif

                        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900">
                            <p class="font-semibold">Local face comparison: {{ $selected->face_similarity !== null ? number_format($selected->face_similarity, 4) : 'unavailable' }}</p>
                            <p class="mt-1">This is a raw cosine similarity score, not a confidence percentage or proof of identity. It can be wrong. Compare the photos yourself. A selfie photo does not prove liveness, and a score never approves an account automatically.</p>
                        </div>

                        @if ($selected->status === 'pending')
                            <div class="grid gap-4 sm:grid-cols-2">
                                @foreach (['front' => 'National ID front', 'selfie' => 'Selfie', 'back' => 'National ID back'] as $kind => $label)
                                    @if ($selected->{$kind.'_path'})
                                        <figure class="min-w-0 overflow-hidden rounded-xl border border-slate-200">
                                            <a href="{{ route('identity.documents', ['submission' => $selected->id, 'kind' => $kind]) }}" target="_blank" rel="noopener noreferrer" class="block bg-slate-100 focus:outline-none focus:ring-4 focus:ring-blue-200">
                                                <img src="{{ route('identity.documents', ['submission' => $selected->id, 'kind' => $kind]) }}" alt="{{ $label }} submitted for private administrator review" class="h-64 w-full object-contain" />
                                                <span class="sr-only">Open {{ strtolower($label) }} in a new tab</span>
                                            </a>
                                            <figcaption class="border-t border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700">{{ $label }} <span class="font-normal text-slate-500">· Open to inspect</span></figcaption>
                                        </figure>
                                    @endif
                                @endforeach
                            </div>

                            <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm leading-6 text-blue-900">
                                <p>Use the submitted QR image in the official checker, then compare its result with the ID and the account details above. A Lendly approval records your administrator review; it must not be described as a government-issued badge.</p>
                            </div>

                            <form wire:submit="review('approved')" class="space-y-5 border-t border-slate-100 pt-5">
                                <fieldset class="space-y-3">
                                    <legend class="mb-3 font-bold text-slate-900">Required checks for approval</legend>
                                    <label class="flex items-start gap-3 text-sm leading-6 text-slate-700"><input type="checkbox" wire:model="qrChecked" class="mt-1 rounded border-slate-300 text-blue-600 focus:ring-blue-500" /><span>I checked the submitted National ID QR code with the official checker and it passed.</span><x-required-mark /></label>
                                    <label class="flex items-start gap-3 text-sm leading-6 text-slate-700"><input type="checkbox" wire:model="detailsMatch" class="mt-1 rounded border-slate-300 text-blue-600 focus:ring-blue-500" /><span>The official result, submitted ID details and current Lendly account name match.</span><x-required-mark /></label>
                                    <label class="flex items-start gap-3 text-sm leading-6 text-slate-700"><input type="checkbox" wire:model="faceReviewed" class="mt-1 rounded border-slate-300 text-blue-600 focus:ring-blue-500" /><span>I visually compared the ID portrait and selfie and reviewed the face similarity result.</span><x-required-mark /></label>
                                </fieldset>
                                <div>
                                    <x-input-label required for="identity-review-notes" value="Review notes (required when rejecting)" />
                                    <textarea id="identity-review-notes" wire:model="notes" maxlength="1000" rows="3" class="mt-2 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" aria-describedby="identity-review-notes-help"></textarea>
                                    <p id="identity-review-notes-help" class="mt-2 text-xs leading-5 text-slate-500">Give a clear reason the member can address. Rejection notes are shown to them. Up to 1,000 characters.</p>
                                </div>
                                <p class="text-xs leading-5 text-slate-500">Saving a decision deletes the submitted photos. The decision, reviewer, notes and similarity score remain in the review record.</p>
                                <div class="flex flex-wrap gap-3">
                                    <x-primary-button wire:loading.attr="disabled" wire:target="review" class="min-h-11">Approve ID review</x-primary-button>
                                    <button type="button" wire:click="review('rejected')" wire:loading.attr="disabled" wire:target="review" class="min-h-11 rounded-lg border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-100 focus:outline-none focus:ring-4 focus:ring-rose-100 disabled:opacity-50">Reject with reason</button>
                                </div>
                                <p wire:loading wire:target="review" class="text-sm text-blue-700" role="status">Saving review…</p>
                            </form>
                        @else
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm leading-6 text-slate-600">
                                <p>Review photos are no longer available after a decision or cancellation.</p>
                                @if ($selected->reviewed_at)<p class="mt-2">Reviewed {{ $selected->reviewed_at->format('M j, Y g:i A') }} by {{ $selected->reviewer?->name ?? 'Unavailable administrator' }}.</p>@endif
                                @if ($selected->review_notes)<p class="mt-2 whitespace-pre-line">{{ $selected->review_notes }}</p>@endif
                            </div>
                        @endif
                    </div>
                </section>
            @else
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm leading-6 text-slate-500">Select a submission to inspect its documents and record a review.</div>
            @endif
        </div>
    </div>
</div>
