<?php

namespace App\Livewire\Owner\Payments;

use App\Models\OwnerPaymentSetting;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class Settings extends Component
{
    use WithFileUploads;

    public string $provider = 'maya';

    public string $account_name = '';

    public string $instructions = '';

    public bool $enabled = true;

    public bool $showForm = false;

    #[Locked]
    public ?int $editingId = null;

    public $qr_image = null;

    public function mount(): void
    {
        $this->member();
    }

    public function addWallet(): void
    {
        $this->member();
        $this->clearForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $settings = OwnerPaymentSetting::where('user_id', $this->member()->id)->findOrFail($id);
        $this->clearForm();
        $this->editingId = $settings->id;
        $this->provider = $settings->provider;
        $this->account_name = $settings->account_name;
        $this->instructions = $settings->instructions ?? '';
        $this->enabled = $settings->enabled;
        $this->showForm = true;
    }

    public function cancel(): void
    {
        $this->member();
        $this->clearForm();
    }

    public function save(): void
    {
        $this->member();
        $this->account_name = trim($this->account_name);
        $this->instructions = trim($this->instructions);
        $validated = $this->validate([
            'provider' => ['required', Rule::in(array_keys(OwnerPaymentSetting::PROVIDERS))],
            'account_name' => ['required', 'string', 'max:120'],
            'instructions' => ['nullable', 'string', 'max:1000'],
            'enabled' => ['boolean'],
            'qr_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'mimetypes:image/jpeg,image/png', 'max:5120', 'dimensions:max_width=4096,max_height=4096'],
        ]);
        unset($validated['qr_image']);
        $validated['instructions'] = $validated['instructions'] ?: null;
        $storedPath = null;
        $oldPath = null;

        try {
            DB::transaction(function () use ($validated, &$storedPath, &$oldPath) {
                $member = $this->member(lock: true);
                $settings = $this->editingId === null
                    ? new OwnerPaymentSetting(['user_id' => $member->id])
                    : OwnerPaymentSetting::where('user_id', $member->id)->lockForUpdate()->findOrFail($this->editingId);
                $oldPath = $settings->qr_path;

                if ($this->qr_image) {
                    $storedPath = $this->qr_image->store('owner-payment-qr', 'local');
                    if (! $storedPath) {
                        throw ValidationException::withMessages(['qr_image' => 'Your QR image could not be saved. Please try again.']);
                    }
                    $settings->qr_path = $storedPath;
                }

                if ((! $settings->exists || $validated['enabled']) && (! $settings->qr_path || ! Storage::disk('local')->exists($settings->qr_path))) {
                    throw ValidationException::withMessages(['qr_image' => 'Upload your payment QR image before saving this e-wallet.']);
                }

                $settings->fill($validated)->save();
            });
        } catch (\Throwable $exception) {
            if ($storedPath) {
                Storage::disk('local')->delete($storedPath);
            }
            throw $exception;
        }

        if ($storedPath && $oldPath && $oldPath !== $storedPath) {
            OwnerPaymentSetting::deleteUnreferencedQr($oldPath);
        }
        $this->clearForm();
        session()->flash('status', 'Payment QR settings saved.');
    }

    public function removeQr(int $id): void
    {
        $oldPath = DB::transaction(function () use ($id) {
            $member = $this->member(lock: true);
            $settings = OwnerPaymentSetting::where('user_id', $member->id)->lockForUpdate()->findOrFail($id);
            $oldPath = $settings->qr_path;
            $settings->delete();

            return $oldPath;
        });

        if ($oldPath) {
            OwnerPaymentSetting::deleteUnreferencedQr($oldPath);
        }
        if ($this->editingId === $id) {
            $this->clearForm();
        }
        session()->flash('status', 'E-wallet removed. Existing bookings keep their original payment instructions.');
    }

    private function clearForm(): void
    {
        $this->reset('provider', 'account_name', 'instructions', 'enabled', 'qr_image', 'editingId', 'showForm');
        $this->resetValidation();
    }

    private function member(bool $lock = false): User
    {
        $query = User::query();
        if ($lock) {
            $query->lockForUpdate();
        }
        $member = $query->find(auth()->id());
        abort_unless($member && ! $member->isAdmin() && ! $member->isSuspended() && $member->hasVerifiedEmail(), 403);

        return $member;
    }

    public function render(): View
    {
        $member = $this->member();

        $wallets = OwnerPaymentSetting::where('user_id', $member->id)->latest('id')->get();

        return view('livewire.owner.payments.settings', [
            'wallets' => $wallets,
            'editingWallet' => $wallets->firstWhere('id', $this->editingId),
            'providers' => OwnerPaymentSetting::PROVIDERS,
        ]);
    }
}
