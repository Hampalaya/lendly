<div>
    <x-page-header eyebrow="Administration" title="Damage reports" subtitle="Review damage claims filed by owners. Disputed claims await resolution in a later phase." />
    <div class="px-4 pt-3 sm:px-6 lg:px-8">
        <x-record-toolbar :filter="$filter" :sort="$recordSort" :filters="['all' => 'All Statuses', 'disputed' => 'Disputed', 'pending' => 'Pending', 'accepted' => 'Accepted']" placeholder="Search damage reports..." search-label="Search damage reports" label="Damage report controls" />
    </div>

    <div class="w-full px-4 pb-6 pt-3 sm:px-6 lg:px-8">
        @if ($damageReports->isEmpty())
            <x-empty-state title="Nothing here" message="No damage reports match this filter right now." />
        @else
            <div class="record-list">
                @foreach ($damageReports as $report)
                    <x-record-row :listing="$report->rental->listing" wire:key="report-{{ $report->id }}">
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                            <h2 class="text-sm font-semibold text-slate-800">{{ $report->rental->listing->name }}</h2>
                            <x-badge :color="$report->status->badgeColor()">{{ $report->status->label() }}</x-badge>
                        </div>
                        <p class="mt-1 text-sm text-slate-500">{{ $report->damage_type }}: {{ $report->description }}</p>
                        <p class="mt-1 text-xs text-slate-500">Owner: {{ $report->rental->owner->name }} &middot; Renter: {{ $report->rental->renter->name }}</p>
                        <p class="mt-1 text-xs text-slate-500">Estimated repair &#8369;{{ number_format($report->estimated_repair_cost, 2) }} &middot; Proposed deduction &#8369;{{ number_format($report->proposed_deduction, 2) }}</p>
                        @if ($report->renter_response_notes)
                            <p class="mt-2 text-sm text-rose-600">Renter's dispute: {{ $report->renter_response_notes }}</p>
                        @endif

                        @if ($report->photos->isNotEmpty())
                            <x-slot:footer>
                                <div class="grid grid-cols-3 gap-2 sm:grid-cols-6">
                                    @foreach ($report->photos as $photo)
                                        <img src="{{ $photo->url() }}" alt="Damage evidence photo {{ $loop->iteration }}" loading="lazy" class="aspect-square w-full rounded-lg object-cover">
                                    @endforeach
                                </div>
                            </x-slot:footer>
                        @endif
                    </x-record-row>
                @endforeach
            </div>
            <div class="mt-4">{{ $damageReports->links() }}</div>
        @endif
    </div>
</div>
