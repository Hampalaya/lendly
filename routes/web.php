<?php

use App\Http\Controllers\IdentityDocumentController;
use App\Http\Controllers\OwnerPaymentQrController;
use App\Http\Controllers\PaymentProofController;
use App\Livewire\Admin\DamageReports\Index as AdminDamageReportsIndex;
use App\Livewire\Admin\Dashboard as AdminDashboard;
use App\Livewire\Admin\Disputes\Index as AdminDisputesIndex;
use App\Livewire\Admin\IdentitySubmissions\Index as AdminIdentitySubmissionsIndex;
use App\Livewire\Admin\Listings\Index as AdminListingsIndex;
use App\Livewire\Admin\Payments\Settings as AdminPaymentSettings;
use App\Livewire\Admin\Rentals\Index as AdminRentalsIndex;
use App\Livewire\Admin\Rentals\Show as AdminRentalShow;
use App\Livewire\Identity\Verify as VerifyIdentity;
use App\Livewire\Listings\Browse as ListingsBrowse;
use App\Livewire\Listings\Map as ListingsMap;
use App\Livewire\Listings\Show as ListingsShow;
use App\Livewire\Member\Dashboard as MemberDashboard;
use App\Livewire\Messages\Index as MessagesIndex;
use App\Livewire\Messages\ListingChat;
use App\Livewire\Messages\ListingShow as ListingMessagesShow;
use App\Livewire\Messages\Show as MessagesShow;
use App\Livewire\Notifications\Index as NotificationsIndex;
use App\Livewire\Owner\Listings\Form as OwnerListingForm;
use App\Livewire\Owner\Listings\Index as OwnerListingsIndex;
use App\Livewire\Owner\Payments\Settings as OwnerPaymentSettings;
use App\Livewire\Owner\RentalRequests\Index as OwnerRentalRequestsIndex;
use App\Livewire\Owner\Rentals\Index as OwnerRentalsIndex;
use App\Livewire\Owner\Rentals\Show as OwnerRentalsShow;
use App\Livewire\RentalRequests\Agreement as RentalRequestAgreement;
use App\Livewire\RentalRequests\Create as RentalRequestCreate;
use App\Livewire\Renter\Cart\Index;
use App\Livewire\Renter\RentalRequests\Index as RenterRentalRequestsIndex;
use App\Livewire\Renter\Rentals\Index as RenterRentalsIndex;
use App\Livewire\Renter\Rentals\Show as RenterRentalsShow;
use App\Livewire\Users\Show as PublicUserShow;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::post('interface', \App\Http\Controllers\SwitchInterfaceController::class)
    ->middleware(['auth', 'verified'])->name('interface.switch');

Route::get('dashboard', MemberDashboard::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('rentals/{rental}/payment-qr', [OwnerPaymentQrController::class, 'booking'])->name('rentals.payment-qr');
    Route::get('identity/verify', VerifyIdentity::class)->name('identity.verify');
    Route::get('identity/documents/{submission}/{kind}', IdentityDocumentController::class)
        ->where('kind', 'front|back|selfie')->name('identity.documents');
    Route::get('payment-proofs/{submission}', PaymentProofController::class)->name('payment-proofs.show');
    Route::get('notifications', NotificationsIndex::class)->name('notifications.index');
    Route::get('messages', MessagesIndex::class)->name('messages.index');
    Route::get('listings/{listing}/contact', ListingMessagesShow::class)->middleware('role:renter')->name('listings.contact');
    Route::get('messages/listings/{conversation}', ListingMessagesShow::class)->name('messages.listing');
    Route::get('listings/{listing}/message', ListingChat::class)->name('listings.message');
    Route::get('listing-conversations/{conversation}', ListingChat::class)->name('listing-conversations.show');
    Route::get('rental-requests/{rentalRequest}/chat', MessagesShow::class)->name('rental-requests.chat');
});

Route::get('listings', ListingsBrowse::class)->name('listings.index');
Route::get('map', ListingsMap::class)->name('map');
Route::get('listings/{listing}', ListingsShow::class)->name('listings.show');
Route::get('users/{user}', PublicUserShow::class)->name('users.show');

Route::middleware(['auth', 'verified', 'role:renter'])->prefix('renter')->name('renter.')->group(function () {
    Route::get('cart', Index::class)->name('cart.index');
    Route::get('dashboard', MemberDashboard::class)->name('dashboard');
    Route::get('listings/{listing}/request', RentalRequestCreate::class)->name('rental-requests.create');
    Route::get('rental-requests', RenterRentalRequestsIndex::class)->name('rental-requests.index');
    Route::get('rental-requests/{rentalRequest}/agreement', RentalRequestAgreement::class)->name('rental-requests.agreement');

    Route::get('rentals', RenterRentalsIndex::class)->name('rentals.index');
    Route::get('rentals/{rental}', RenterRentalsShow::class)->name('rentals.show');
});

Route::middleware(['auth', 'verified', 'role:owner'])->prefix('owner')->name('owner.')->group(function () {
    Route::get('payments', OwnerPaymentSettings::class)->name('payments.settings');
    Route::get('payments/qr/{wallet?}', [OwnerPaymentQrController::class, 'current'])->whereNumber('wallet')->name('payments.qr');
    Route::get('dashboard', MemberDashboard::class)->name('dashboard');
    Route::get('listings', OwnerListingsIndex::class)->name('listings.index');
    Route::get('listings/create', OwnerListingForm::class)->name('listings.create');
    Route::get('listings/{listing}/edit', OwnerListingForm::class)->name('listings.edit');

    Route::get('rental-requests', OwnerRentalRequestsIndex::class)->name('rental-requests.index');
    Route::get('rental-requests/{rentalRequest}/agreement', RentalRequestAgreement::class)->name('rental-requests.agreement');
    Route::get('rentals', OwnerRentalsIndex::class)->name('rentals.index');
    Route::get('rentals/{rental}', OwnerRentalsShow::class)->name('rentals.show');
});

Route::middleware(['auth', 'verified', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('dashboard', AdminDashboard::class)->name('dashboard');
    Route::get('identity-submissions', AdminIdentitySubmissionsIndex::class)->name('identity-submissions.index');
    Route::get('listings', AdminListingsIndex::class)->name('listings.index');
    Route::get('rentals', AdminRentalsIndex::class)->name('rentals.index');
    Route::get('rentals/{rental}', AdminRentalShow::class)->name('rentals.show');
    Route::get('payment-settings', AdminPaymentSettings::class)->name('payments.settings');
    Route::get('damage-reports', AdminDamageReportsIndex::class)->name('damage-reports.index');
    Route::get('disputes', AdminDisputesIndex::class)->name('disputes.index');
});

require __DIR__.'/auth.php';
