<?php

namespace App\Livewire\Admin\DamageReports;

use App\Livewire\Concerns\FiltersRecords;
use App\Models\DamageReport;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use FiltersRecords;
    use WithPagination;

    public string $filter = 'disputed';

    public function updatingFilter(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $admin = User::find(auth()->id());
        abort_unless($admin && $admin->isAdmin() && ! $admin->isSuspended() && $admin->hasVerifiedEmail(), 403);
        $damageReports = $this->filterRecords(DamageReport::query()
            ->when($this->filter !== 'all', fn ($query) => $query->where('status', $this->filter))
            ->with(['rental.listing.images', 'rental.owner', 'rental.renter', 'photos'])
            ->orderByDesc('created_at'), ['description', 'damage_type'], ['rental.listing' => ['name'], 'rental.owner' => ['name'], 'rental.renter' => ['name']])->paginate(10);

        return view('livewire.admin.damage-reports.index', ['damageReports' => $damageReports]);
    }
}
