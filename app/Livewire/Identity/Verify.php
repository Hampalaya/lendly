<?php

namespace App\Livewire\Identity;

use App\Models\IdentitySubmission;
use App\Models\User;
use App\Services\ManualIdentityReview;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class Verify extends Component
{
    use WithFileUploads;

    public string $documentType = '';

    public $front;

    public $back;

    public $selfie;

    public bool $consent = false;

    public function mount(): void
    {
        $this->member();
    }

    public function submit(): void
    {
        $member = $this->member();
        $this->validate([
            'front' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:min_width=200,min_height=200,max_width=6000,max_height=6000'],
            'back' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:min_width=200,min_height=200,max_width=6000,max_height=6000'],
            'selfie' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:min_width=200,min_height=200,max_width=6000,max_height=6000'],
            'consent' => ['accepted'],
        ], ['consent.accepted' => 'Please consent to the private ID review and face comparison before submitting.']);

        app(ManualIdentityReview::class)->submit($member, $this->front, $this->back, $this->selfie, $this->consent, $this->documentType);
        foreach ([$this->front, $this->back, $this->selfie] as $upload) {
            $upload?->delete();
        }
        $this->reset('front', 'back', 'selfie', 'consent', 'documentType');
        $this->dispatch('identity-submitted');
    }

    private function member(): User
    {
        $member = User::find(auth()->id());
        abort_unless($member && ! $member->isAdmin() && ! $member->isSuspended() && $member->hasVerifiedEmail(), 403);

        return $member;
    }

    public function render(): View
    {
        $member = $this->member();

        return view('livewire.identity.verify', [
            'member' => $member,
            'documentTypes' => IdentitySubmission::DOCUMENT_TYPES,
            'verified' => $member->hasVerifiedIdentity(),
            'submission' => IdentitySubmission::where('user_id', $member->id)->latest('id')->first(),
        ]);
    }
}
