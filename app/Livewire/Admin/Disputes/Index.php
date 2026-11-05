<?php

namespace App\Livewire\Admin\Disputes;

use App\Enums\DisputeResolution;
use App\Livewire\Concerns\FiltersRecords;
use App\Models\Dispute;
use App\Services\RentalLifecycle;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use FiltersRecords;
    use WithPagination;

    #[Url]
    public string $filter = 'open';

    #[Url]
    public ?int $rentalId = null;

    #[Url]
    public ?int $rental = null;

    public function updatingRentalId(): void
    {
        $this->resetPage();
    }

    public ?int $resolving = null;

    public string $resolution = '';

    public string $resolution_notes = '';

    public ?float $partial_amount = null;

    public string $other_resolution = '';

    public function updatingFilter(): void
    {
        $this->resetPage();
    }

    public function startResolving(int $disputeId): void
    {
        $this->resolving = $disputeId;
        $this->resolution = '';
        $this->resolution_notes = '';
        $this->partial_amount = null;
        $this->other_resolution = '';
    }

    public function confirmResolve(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        $dispute = Dispute::with(['rental.securityDeposit', 'damageReport'])->findOrFail($this->resolving);

        $rules = [
            'resolution' => ['required', Rule::enum(DisputeResolution::class)],
            'resolution_notes' => ['nullable', 'string', 'max:1000'],
            'other_resolution' => ['required_if:resolution,other', 'nullable', 'string', 'max:1000'],
        ];

        if ($dispute->damage_report_id && $this->resolution === DisputeResolution::PartialCompensation->value) {
            $rules['partial_amount'] = ['required', 'numeric', 'min:0', 'max:'.$dispute->rental->securityDeposit?->amount];
        }

        $this->validate($rules);

        RentalLifecycle::resolveDispute(
            $dispute,
            DisputeResolution::from($this->resolution),
            $this->resolution_notes,
            auth()->user(),
            $this->partial_amount,
            $this->other_resolution,
        );

        $this->resolving = null;
        $this->reset('resolution', 'resolution_notes', 'partial_amount', 'other_resolution');
    }

    public function render(): View
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        $disputes = $this->filterRecords(Dispute::query()
            ->when(($this->rentalId ?? $this->rental) !== null, fn ($query) => $query->where('rental_id', $this->rentalId ?? $this->rental))
            ->when($this->filter !== 'all', fn ($query) => $query->where('status', $this->filter))
            ->with(['rental.listing.images', 'rental.owner', 'rental.renter', 'raisedBy', 'damageReport.photos'])
            ->orderByDesc('created_at'), ['description', 'other_resolution', 'resolution_notes'], ['rental.listing' => ['name'], 'raisedBy' => ['name'], 'rental.owner' => ['name'], 'rental.renter' => ['name']])->paginate(10);

        return view('livewire.admin.disputes.index', ['disputes' => $disputes]);
    }
}
