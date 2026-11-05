<div>
    <x-page-header eyebrow="Owner" title="Payment QR settings" subtitle="Manage the e-wallets renters can use to pay you." />

    <div class="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <div class="rounded-2xl border border-blue-100 bg-blue-50 p-5 text-sm leading-6 text-blue-900">
            <p class="font-semibold">Your wallet, your payment QR</p>
            <p class="mt-1">Upload the payment QR from your own wallet app and check the recipient name. Renters pay outside Lendly and submit proof. You must check your wallet and confirm the funds arrived; uploading a QR or proof does not mark a rental paid.</p>
        </div>

        @if (session('status'))
            <p role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm leading-6 text-emerald-800">{{ session('status') }}</p>
        @endif

        <section aria-labelledby="saved-payment-qr-heading" class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="saved-payment-qr-heading" class="text-lg font-bold tracking-tight text-slate-900">Saved Payment QR</h2>
                <button type="button" wire:click="addWallet" wire:loading.attr="disabled" wire:target="addWallet,edit,save,removeQr,qr_image" aria-expanded="{{ $showForm ? 'true' : 'false' }}" aria-controls="owner-wallet-form" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-100 disabled:opacity-50">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
                    Add E-Wallet
                </button>
            </div>

            @if ($showForm)
                <section id="owner-wallet-form" aria-labelledby="owner-wallet-form-heading" wire:key="wallet-form-{{ $editingId ?? 'new' }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" x-data x-init="$nextTick(() => $el.querySelector('select').focus())">
                    <div class="mb-5 flex items-start justify-between gap-3">
                        <div>
                            <h3 id="owner-wallet-form-heading" class="font-semibold text-slate-900">E-wallet payment details</h3>
                            <p class="mt-1 text-sm text-slate-500">{{ $editingId ? 'Update this wallet and its payment QR.' : 'Add another wallet and its payment QR. You can save as many as you need.' }}</p>
                        </div>
                        <button type="button" wire:click="cancel" wire:loading.attr="disabled" wire:target="save,qr_image" aria-label="Close e-wallet form" title="Close" class="grid h-9 w-9 shrink-0 place-items-center rounded-lg text-slate-500 transition hover:bg-slate-100 focus:outline-none focus:ring-4 focus:ring-blue-100">
                            <x-icon name="x-mark" class="h-5 w-5" aria-hidden="true" />
                        </button>
                    </div>
                    <form wire:submit="save" class="space-y-5">
                        <div class="grid items-start gap-5 lg:grid-cols-3">
                            <div class="space-y-4 lg:col-span-2">
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <x-input-label required for="owner-payment-provider" value="E-wallet" />
                                        <select id="owner-payment-provider" wire:model="provider" class="mt-2 min-h-11 w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500" aria-invalid="{{ $errors->has('provider') ? 'true' : 'false' }}">
                                            @foreach ($providers as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <x-input-error :messages="$errors->get('provider')" class="mt-2" />
                                    </div>
                                    <div>
                                        <x-input-label required for="owner-payment-account-name" value="Wallet account name" />
                                        <x-text-input id="owner-payment-account-name" wire:model="account_name" type="text" maxlength="120" required autocomplete="name" placeholder="Name shown in your wallet app" class="mt-2 min-h-11 w-full text-sm" aria-describedby="owner-payment-name-help" aria-invalid="{{ $errors->has('account_name') ? 'true' : 'false' }}" />
                                        <p id="owner-payment-name-help" class="mt-1.5 text-xs leading-5 text-slate-500">Use the recipient name renters should see before paying.</p>
                                        <x-input-error :messages="$errors->get('account_name')" class="mt-2" />
                                    </div>
                                </div>
                                <div>
                                    <x-input-label for="owner-payment-instructions" value="Additional instructions (optional)" />
                                    <textarea id="owner-payment-instructions" wire:model="instructions" rows="2" maxlength="1000" placeholder="For example, include your booking number as the payment reference." class="mt-2 w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500" aria-describedby="owner-payment-instructions-help" aria-invalid="{{ $errors->has('instructions') ? 'true' : 'false' }}"></textarea>
                                    <p id="owner-payment-instructions-help" class="mt-1 text-xs leading-5 text-slate-500">Never share your wallet PIN or one-time codes.</p>
                                    <x-input-error :messages="$errors->get('instructions')" class="mt-2" />
                                </div>
                            </div>
                            <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4">
                                <x-input-label :required="! $editingWallet?->qr_path" for="owner-payment-qr" value="Your payment QR image" />
                                <div class="mt-3 flex items-center gap-3">
                                    @if ($qr_image && ! $errors->has('qr_image') && in_array($qr_image->getMimeType(), ['image/png', 'image/jpeg'], true))
                                        <img src="{{ $qr_image->temporaryUrl() }}" alt="Selected payment QR preview" class="h-20 w-20 shrink-0 rounded-lg border border-slate-200 bg-white p-1 object-contain" />
                                    @elseif ($editingWallet?->qr_path)
                                        <img src="{{ route('owner.payments.qr', ['wallet' => $editingWallet->id]) }}" alt="Current {{ $editingWallet->providerLabel() }} payment QR" class="h-20 w-20 shrink-0 rounded-lg border border-slate-200 bg-white p-1 object-contain" />
                                    @else
                                        <span class="grid h-16 w-16 shrink-0 place-items-center rounded-lg border border-slate-200 bg-white text-slate-400"><x-icon name="camera" class="h-7 w-7" aria-hidden="true" /></span>
                                    @endif
                                    <p id="owner-payment-qr-help" class="text-xs leading-5 text-slate-500">Upload a clear QR from your wallet app.<br />JPG or PNG, up to 5 MB and 4096 x 4096 pixels.</p>
                                </div>
                                <input id="owner-payment-qr" wire:model="qr_image" type="file" accept="image/jpeg,image/png" class="mt-3 block w-full min-w-0 rounded-lg border border-slate-300 bg-white text-xs text-slate-600 file:mr-2 file:cursor-pointer file:border-0 file:bg-blue-50 file:px-3 file:py-2.5 file:font-semibold file:text-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-100" aria-describedby="owner-payment-qr-help" aria-invalid="{{ $errors->has('qr_image') ? 'true' : 'false' }}" />
                                <p wire:loading wire:target="qr_image" role="status" class="mt-2 text-xs text-blue-700">Uploading your QR image.</p>
                                <x-input-error :messages="$errors->get('qr_image')" class="mt-2" />
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center justify-between gap-4 border-t border-slate-100 pt-4">
                            <div>
                                <label class="flex cursor-pointer items-center gap-2.5 text-sm text-slate-700">
                                    <input wire:model="enabled" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" aria-describedby="owner-payment-enabled-help" />
                                    Use this wallet for new booking payment instructions
                                </label>
                                <p id="owner-payment-enabled-help" class="mt-1.5 text-xs leading-5 text-slate-500">Existing bookings keep their original recipient and QR after you edit or remove a wallet.</p>
                                <x-input-error :messages="$errors->get('enabled')" class="mt-2" />
                            </div>
                            <div class="ml-auto flex items-center gap-2">
                                <button type="button" wire:click="cancel" wire:loading.attr="disabled" wire:target="save,qr_image" class="min-h-11 rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-blue-100">Cancel</button>
                                <x-primary-button class="min-h-11" wire:loading.attr="disabled" wire:target="save,removeQr,qr_image">{{ $editingId ? 'Save changes' : 'Save E-Wallet' }}</x-primary-button>
                            </div>
                        </div>
                    </form>
                </section>
            @endif

            @if ($wallets->isNotEmpty())
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach ($wallets as $wallet)
                        <article wire:key="wallet-{{ $wallet->id }}-{{ md5($wallet->qr_path ?? '') }}" class="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div class="border-b border-slate-100 px-4 py-3">
                                <h3 class="font-bold text-slate-900">{{ $wallet->providerLabel() }}</h3>
                                <p class="mt-1 break-words text-xs text-slate-500">{{ $wallet->account_name }}</p>
                            </div>
                            <div class="p-3">
                                @if ($wallet->qr_path)
                                    <img src="{{ route('owner.payments.qr', ['wallet' => $wallet->id]) }}" alt="{{ $wallet->providerLabel() }} payment QR for {{ $wallet->account_name }}" loading="lazy" decoding="async" class="mx-auto h-44 w-full max-w-48 rounded-lg border border-slate-100 bg-white p-2 object-contain" />
                                @else
                                    <div class="mx-auto grid h-44 w-full max-w-48 place-content-center rounded-lg border border-dashed border-slate-200 bg-slate-50 p-3 text-center text-xs text-slate-500">Edit this wallet to upload a payment QR.</div>
                                @endif
                            </div>
                            <div class="flex justify-end gap-2 border-t border-slate-100 px-4 py-2">
                                <button type="button" wire:click="edit({{ $wallet->id }})" wire:loading.attr="disabled" wire:target="edit,addWallet,save,removeQr,qr_image" aria-label="Edit {{ $wallet->providerLabel() }} wallet for {{ $wallet->account_name }}" title="Edit e-wallet" class="grid h-10 w-10 place-items-center rounded-lg border border-slate-200 text-slate-600 transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-100 disabled:opacity-50">
                                    <x-icon name="pencil" class="h-4 w-4" aria-hidden="true" />
                                </button>
                                <button type="button" wire:click="removeQr({{ $wallet->id }})" wire:confirm="Remove this e-wallet and its saved QR? Existing bookings keep their original payment instructions." wire:loading.attr="disabled" wire:target="edit,addWallet,save,removeQr,qr_image" aria-label="Remove {{ $wallet->providerLabel() }} wallet for {{ $wallet->account_name }}" title="Remove e-wallet" class="grid h-10 w-10 place-items-center rounded-lg border border-red-200 text-red-600 transition hover:bg-red-50 focus:outline-none focus:ring-4 focus:ring-red-100 disabled:opacity-50">
                                    <x-icon name="trash" class="h-4 w-4" aria-hidden="true" />
                                </button>
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-5 py-12 text-center">
                    <x-icon name="camera" class="mx-auto h-9 w-9 text-slate-400" aria-hidden="true" />
                    <p class="mt-3 text-sm font-semibold text-slate-700">No e-wallets saved yet</p>
                    <p class="mt-1 text-sm text-slate-500">Click Add E-Wallet to save your first payment QR.</p>
                </div>
            @endif
        </section>

        <footer class="space-y-2 pb-2 text-xs leading-6 text-slate-500">
            <p>This saved image is shown through your private account. After enabling, eligible renters can view it on their booking.</p>
            <p>For wallet payments, you receive the full booking total, including the processing fee and refundable deposit. Processing-fee remittance and deposit refunds remain manual.</p>
        </footer>
    </div>
</div>
