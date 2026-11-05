<?php

namespace App\Livewire\Admin\IdentitySubmissions;

use App\Livewire\Concerns\FiltersRecords;
use App\Models\IdentitySubmission;
use App\Models\User;
use App\Services\ManualIdentityReview;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use FiltersRecords;
    use WithPagination;

    public string $filter = 'pending';

    #[Locked]
    public ?int $selectedId = null;

    public string $notes = '';

    public bool $qrChecked = false;

    public bool $detailsMatch = false;

    public bool $faceReviewed = false;

    public function mount(): void
    {
        $this->admin();
    }

    public function updatingFilter(string $filter): void
    {
        $this->admin();
        abort_unless(in_array($filter, ['pending', 'approved', 'rejected', 'cancelled', 'all'], true), 403);
        $this->resetPage();
    }

    public function selectSubmission(int $id): void
    {
        $this->admin();
        IdentitySubmission::findOrFail($id);
        $this->selectedId = $id;
        $this->reset('notes', 'qrChecked', 'detailsMatch', 'faceReviewed');
        $this->resetErrorBag();
    }

    public function review(string $decision): void
    {
        $admin = $this->admin();
        abort_unless($this->selectedId !== null, 403);
        app(ManualIdentityReview::class)->review(
            $admin, $this->selectedId, $decision, $this->notes,
            $this->qrChecked, $this->detailsMatch, $this->faceReviewed,
        );
        $this->reset('notes', 'qrChecked', 'detailsMatch', 'faceReviewed');
        session()->flash('status', 'Identity review saved.');
    }

    private function admin(): User
    {
        $admin = User::find(auth()->id());
        abort_unless($admin && $admin->isAdmin() && ! $admin->isSuspended() && $admin->hasVerifiedEmail(), 403);

        return $admin;
    }

    protected function effectiveRecordSort(): string
    {
        return $this->recordSort === 'oldest' || ($this->recordSort === 'default' && $this->filter === 'pending')
            ? 'oldest'
            : 'newest';
    }

    public function render(): View
    {
        $this->admin();
        abort_unless(in_array($this->filter, ['pending', 'approved', 'rejected', 'cancelled', 'all'], true), 403);

        return view('livewire.admin.identity-submissions.index', [
            'sort' => $this->effectiveRecordSort(),
            'submissions' => $this->filterRecords(IdentitySubmission::query()->with('user')
                ->when($this->filter !== 'all', fn ($query) => $query->where('status', $this->filter))
                ->orderBy('id', $this->filter === 'pending' ? 'asc' : 'desc'), ['id'], ['user' => ['name', 'email']])->paginate(10),
            'selected' => $this->selectedId ? IdentitySubmission::with(['user', 'reviewer'])->find($this->selectedId) : null,
        ]);
    }
}
