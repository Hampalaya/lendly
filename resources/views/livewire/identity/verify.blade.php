<div x-data="{
    stream: null, cameraOpen: false, uploading: false, cameraError: '', cameraNotice: '',
    async startCamera() {
        this.cameraError = ''; this.cameraNotice = '';
        if (!$wire.consent) return;
        if (!navigator.mediaDevices?.getUserMedia) {
            this.cameraError = 'Camera access needs HTTPS or localhost. You can upload a selfie instead.';
            return;
        }
        try {
            this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
            if (!$wire.consent || !this.$root.isConnected) { this.stopCamera(); return; }
            this.cameraOpen = true;
            await this.$nextTick();
            this.$refs.selfieVideo.srcObject = this.stream;
            await this.$refs.selfieVideo.play();
        } catch (error) {
            this.stopCamera();
            this.cameraError = 'The camera could not open. Allow camera access or upload a clear selfie.';
        }
    },
    stopCamera() {
        this.stream?.getTracks().forEach(track => track.stop());
        this.stream = null; this.cameraOpen = false;
    },
    captureSelfie() {
        const video = this.$refs.selfieVideo;
        if (!video.videoWidth || !video.videoHeight) { this.cameraError = 'Wait for the camera preview before taking the photo.'; return; }
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth; canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);
        this.uploading = true;
        this.stopCamera();
        canvas.toBlob(blob => {
            if (!blob || !$wire.consent) { this.uploading = false; return; }
            $wire.upload('selfie', new File([blob], 'selfie.jpg', { type: 'image/jpeg' }),
                () => { this.uploading = false; this.cameraNotice = 'Selfie captured. It will be included when you submit for review.'; },
                () => { this.uploading = false; this.cameraError = 'The selfie could not upload. Please try again or choose a file.'; });
        }, 'image/jpeg', 0.9);
    },
    destroy() { this.stopCamera(); }
}" x-effect="if (!$wire.consent) stopCamera()" @keydown.escape.window="stopCamera()" @identity-submitted.window="stopCamera(); cameraNotice = ''">
    <x-page-header eyebrow="Account" title="ID review" subtitle="Submit a photo ID and selfie for a Lendly administrator to review." />

    <div class="mx-auto max-w-6xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
        <a href="{{ route('profile') }}" wire:navigate class="mb-6 inline-flex min-h-10 items-center gap-2 rounded-lg text-sm font-semibold text-slate-600 hover:text-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-100">
            <x-icon name="chevron-down" class="h-4 w-4 rotate-90" aria-hidden="true" /> Back to profile
        </a>

        @if ($verified)
            <section class="rounded-3xl border border-emerald-200 bg-white p-6 shadow-sm sm:p-10" role="status" aria-labelledby="identity-result-title">
                <p class="text-xs font-bold uppercase tracking-wider text-emerald-700">{{ $member->identity_verification_environment === 'manual' ? 'ID reviewed' : 'Identity verified' }}</p>
                <h2 id="identity-result-title" class="mt-2 text-2xl font-bold text-slate-900">Your identity check is complete.</h2>
                <p class="mt-3 text-sm leading-6 text-slate-600">Your account can rent and list items. This Lendly review covers both interfaces; it is not a government-issued verification badge.</p>
                <a href="{{ route($member->dashboardRouteName()) }}" wire:navigate class="mt-6 inline-flex min-h-11 items-center rounded-lg bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-100">Continue to dashboard</a>
            </section>
        @elseif ($submission?->status === 'pending' && $submission->created_at->gt(now()->subDays(30)))
            <section class="rounded-3xl border border-blue-200 bg-white p-6 shadow-sm sm:p-10" role="status" aria-labelledby="identity-pending-title">
                <p class="text-xs font-bold uppercase tracking-wider text-blue-700">Awaiting administrator review</p>
                <h2 id="identity-pending-title" class="mt-2 text-2xl font-bold text-slate-900">Your ID and selfie have been submitted.</h2>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">An administrator will check your photo ID, account name, and selfie. You can browse while your submission is pending. Renting and listing unlock after approval.</p>
                <p class="mt-3 text-xs text-slate-500">Submitted {{ $submission->created_at->format('M j, Y g:i A') }}. Check this page for the decision.</p>
                <a href="{{ route('listings.index') }}" wire:navigate class="mt-6 inline-flex min-h-11 items-center rounded-lg bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-100">Browse items</a>
            </section>
        @else
            <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
                <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm" aria-labelledby="identity-form-title">
                    <div class="border-b border-slate-100 bg-blue-50/60 p-6 sm:p-7">
                        <h2 id="identity-form-title" class="text-2xl font-bold text-slate-900">Submit your ID for review.</h2>
                        <p class="mt-3 text-sm leading-6 text-slate-600">Use your own school, work, government, or other ID that shows your name and photo. Choose an ID the issuer can confirm. For a Philippine National ID, include the side with its QR code.</p>
                        <p class="mt-2 text-sm leading-6 text-slate-600">Account name: <strong>{{ $member->name }}</strong>. <a href="{{ route('profile') }}" wire:navigate class="font-semibold text-blue-700 underline">Correct your name</a> to match your ID, including your middle name and suffix, before submitting.</p>
                    </div>

                    <form wire:submit="submit" class="space-y-6 p-6 sm:p-7" aria-label="Submit ID for administrator review">
                        @if ($submission?->status === 'rejected')
                            <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm leading-6 text-rose-800" role="status">
                                <p class="font-bold">Your previous submission was not approved.</p>
                                <p class="mt-1 whitespace-pre-line">{{ $submission->review_notes }}</p>
                                <p class="mt-2">Address the reason above, then submit new photos for review.</p>
                            </div>
                        @elseif ($submission && ($submission->status === 'cancelled' || ($submission->status === 'pending' && $submission->created_at->lte(now()->subDays(30)))))
                            <p class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-800" role="status">Your previous submission is no longer active. Check your account details and submit new photos.</p>
                        @endif

                        @if ($errors->any())
                            <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
                                <p class="font-semibold">Your submission needs attention.</p>
                                <ul class="mt-2 list-inside list-disc space-y-1">
                                    @foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach
                                </ul>
                            </div>
                        @endif

                        <section aria-labelledby="identity-consent-title">
                            <h3 id="identity-consent-title" class="font-bold text-slate-900">Privacy and consent</h3>
                            <p id="identity-consent-help" class="mt-2 text-sm leading-6 text-slate-600">Lendly stores the photos privately, tries to read the name from the ID, and compares the portrait with the selfie on its own server. An administrator checks the ID through the official National ID checker or confirms it with its issuer.</p>
                            <p class="mt-2 text-sm leading-6 text-slate-600">Review photos are deleted after a decision or cancellation. Pending review photos expire after 30 days. The review record and decision are retained.</p>
                            <label class="mt-4 flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <input id="identity-consent" type="checkbox" wire:model.live="consent" class="mt-0.5 h-5 w-5 shrink-0 rounded border-slate-300 text-blue-600 focus:ring-blue-500" aria-describedby="identity-consent-help" />
                                <span class="text-sm leading-6 text-slate-700">I consent to private storage, name extraction, face comparison, and administrator review of my photo ID and selfie, including a check with the ID issuer.</span>
                            <x-required-mark /></label>
                            <x-input-error :messages="$errors->get('consent')" class="mt-2" />
                        </section>

                        <fieldset :disabled="!$wire.consent || uploading" wire:loading.attr="disabled" wire:target="submit" class="space-y-5 border-t border-slate-100 pt-5 disabled:opacity-60">
                            <legend class="sr-only">ID photos and selfie</legend>
                            <p id="identity-file-help" class="text-xs leading-5 text-slate-500">Accept the consent above before choosing photos. Use JPG, PNG or WebP, up to 8 MB each and 200–6,000 pixels on each side. Keep the ID text and any QR code readable.</p>
                            <div>
                                <x-input-label required for="identity-document-type" value="Photo ID type" />
                                <select id="identity-document-type" wire:model="documentType" required class="mt-2 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">Choose your ID type</option>
                                    @foreach ($documentTypes as $type => $label)
                                        <option value="{{ $type }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('document_type')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label required for="identity-front" value="Photo ID front" />
                                <input id="identity-front" type="file" wire:model="front" accept="image/jpeg,image/png,image/webp" required aria-describedby="identity-file-help" class="mt-2 block w-full rounded-lg border border-slate-300 text-sm text-slate-600 file:mr-3 file:border-0 file:bg-blue-50 file:px-4 file:py-3 file:font-semibold file:text-blue-700" />
                                <x-input-error :messages="$errors->get('front')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="identity-back" value="Photo ID back (optional)" />
                                <input id="identity-back" type="file" wire:model="back" accept="image/jpeg,image/png,image/webp" aria-describedby="identity-file-help" class="mt-2 block w-full rounded-lg border border-slate-300 text-sm text-slate-600 file:mr-3 file:border-0 file:bg-blue-50 file:px-4 file:py-3 file:font-semibold file:text-blue-700" />
                                <x-input-error :messages="$errors->get('back')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label required for="identity-selfie" value="Clear selfie" />
                                <p class="mt-2 text-sm leading-6 text-slate-500">Face the camera in good light, with your face visible. Take a photo now or upload one.</p>
                                <div class="mt-3 flex flex-wrap gap-3">
                                    <button type="button" @click="startCamera()" :disabled="cameraOpen || uploading" class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-100 focus:outline-none focus:ring-4 focus:ring-blue-100 disabled:opacity-50"><x-icon name="camera" class="h-4 w-4" aria-hidden="true" /> Open selfie camera</button>
                                    @if ($selfie)<span class="self-center text-sm font-semibold text-emerald-700" role="status">Selfie ready</span>@endif
                                </div>
                                <input id="identity-selfie" type="file" wire:model="selfie" accept="image/jpeg,image/png,image/webp" capture="user" aria-describedby="identity-file-help" class="mt-3 block w-full rounded-lg border border-slate-300 text-sm text-slate-600 file:mr-3 file:border-0 file:bg-blue-50 file:px-4 file:py-3 file:font-semibold file:text-blue-700" />
                                <x-input-error :messages="$errors->get('selfie')" class="mt-2" />
                            </div>
                        </fieldset>

                        <div x-cloak x-show="cameraOpen" wire:ignore class="overflow-hidden rounded-xl border border-slate-200 bg-slate-900">
                            <video x-ref="selfieVideo" autoplay muted playsinline class="max-h-80 w-full object-contain" aria-label="Selfie camera preview"></video>
                            <div class="flex flex-wrap gap-3 p-3">
                                <button type="button" @click="captureSelfie()" :disabled="uploading" class="min-h-11 rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-900 focus:outline-none focus:ring-4 focus:ring-blue-300">Take selfie</button>
                                <button type="button" @click="stopCamera()" class="min-h-11 rounded-lg border border-white/50 px-4 py-2 text-sm font-semibold text-white focus:outline-none focus:ring-4 focus:ring-blue-300">Close camera</button>
                            </div>
                        </div>
                        <p x-cloak x-show="cameraError" x-text="cameraError" class="text-sm text-red-700" role="alert"></p>
                        <p x-cloak x-show="cameraNotice" x-text="cameraNotice" class="text-sm text-emerald-700" role="status"></p>
                        <p x-cloak x-show="uploading" class="text-sm text-blue-700" role="status">Uploading captured selfie…</p>
                        <p wire:loading wire:target="front,back,selfie" class="text-sm text-blue-700" role="status">Uploading photo…</p>
                        <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-5">
                            <x-primary-button :disabled="!$consent" x-bind:disabled="!$wire.consent || uploading" wire:loading.attr="disabled" wire:target="submit,front,back,selfie" class="min-h-11"><span wire:loading.remove wire:target="submit">Submit for review</span><span wire:loading wire:target="submit">Submitting…</span></x-primary-button>
                            <a href="{{ route('listings.index') }}" wire:navigate class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 focus:outline-none focus:ring-4 focus:ring-blue-100">Browse for now</a>
                        </div>
                    </form>
                </section>
                <aside class="space-y-4 rounded-2xl border border-blue-100 bg-blue-50/60 p-6">
                    <h2 class="font-bold text-slate-900">What happens next</h2>
                    <ol class="list-inside list-decimal space-y-3 text-sm leading-6 text-slate-600">
                        <li>Upload readable ID photos and a clear selfie.</li>
                        <li>An administrator checks the QR code, account details and face comparison.</li>
                        <li>Approval unlocks renting and listing across both interfaces.</li>
                    </ol>
                    <p class="border-t border-blue-100 pt-4 text-xs leading-6 text-slate-500">You can browse while waiting. Face scores can be wrong. An administrator reviews the photos and never relies on the score alone.</p>
                </aside>
            </div>
        @endif
    </div>
</div>
