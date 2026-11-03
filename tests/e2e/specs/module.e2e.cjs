// Module-level tests: each business module exercised on its own, starting from a prepared state
// (tests/e2e/fixtures/seed.php) instead of replaying the whole rental flow. Covers the unhappy
// paths too: decline, auto-decline, cancellation fees, rejected payments, damage and disputes.
const assert = require('assert');
const a = require('../support/actions.cjs');
const { fixtures, freshSession, loginAs, asAdmin } = require('../support/session.cjs');
const LoginPage = require('../pages/LoginPage.cjs');

let fx;
const reviewBox = () => $('textarea[id^="payment-review-"]');

describe('Module tests', () => {
  before(() => {
    fx = fixtures();
  });

  describe('Listings module (owner)', () => {
    it('IT-01 owner edits a listing price and the new price is public (FR-07)', async () => {
      await loginAs(fx.users.owner.email, 'owner');
      await browser.url(`/owner/listings/${fx.listings.ladder.id}/edit`);
      await a.type('#price_per_day', '275');
      await browser.pause(1200);
      await a.submitWireForm('save');
      await a.waitForText('Listing updated.');
      await browser.url(`/listings/${fx.listings.ladder.id}`);
      await a.waitForText('₱275.00');
    });

    it('IT-02 owner pauses a listing and it disappears from the marketplace (FR-07, FR-08)', async () => {
      await browser.url('/owner/listings');
      await a.clickWire(`deactivate(${fx.listings.ladder.id})`);
      await browser.pause(1500);
      await freshSession();
      await browser.url('/listings');
      await a.type('input[aria-label="Search items"]', fx.listings.ladder.name);
      await a.waitForText('No listings match your filters');
    });

    it('IT-03 owner reactivates the listing and it is public again (FR-07, FR-08)', async () => {
      await loginAs(fx.users.owner.email, 'owner');
      await browser.url('/owner/listings');
      await a.clickWire(`reactivate(${fx.listings.ladder.id})`);
      await browser.pause(1500);
      await freshSession();
      await browser.url('/listings');
      await a.type('input[aria-label="Search items"]', fx.listings.ladder.name);
      await a.waitForText(fx.listings.ladder.name);
    });
  });

  describe('Rental requests module', () => {
    it('IT-04 owner declines a request with a reason (FR-12)', async () => {
      await loginAs(fx.users.owner.email, 'owner');
      await browser.url('/owner/rental-requests');
      await a.clickWire(`startRejecting(${fx.requests.reqDecline})`);
      await a.type('#rejection_reason', 'QA: item is being serviced that week.');
      await a.clickWire('confirmReject');
      await browser.pause(1500);
      await a.assertNoServerError();
    });

    it('IT-05 renter sees the decline and the owner\'s reason (FR-12, FR-13)', async () => {
      await loginAs(fx.users.renter.email, 'renter');
      // The list shows 10 per page; use its status filter to find the declined request.
      await browser.url('/renter/rental-requests?filter=rejected');
      await a.waitForText('QA: item is being serviced that week.');
    });

    it('IT-06 approving one request auto-declines an overlapping request (FR-12)', async () => {
      await loginAs(fx.users.owner.email, 'owner');
      await browser.url('/owner/rental-requests');
      await a.clickWire(`startApproving(${fx.requests.reqOverlapA})`);
      await a.clickWire(`approve(${fx.requests.reqOverlapA})`);
      await a.waitForUrl(new RegExp(`/owner/rental-requests/${fx.requests.reqOverlapA}/agreement`));
      await loginAs(fx.users.renter2.email, 'renter');
      await browser.url('/renter/rental-requests');
      await a.waitForText('These dates were booked by another renter.');
    });

    it('IT-07 renter cancels their own pending request (FR-34)', async () => {
      await a.clickWire(`startCancelling(${fx.requests.reqCancel})`);
      await a.type(`#cancellation-reason-${fx.requests.reqCancel}`, 'QA: plans changed.');
      await a.scrollClick(`form[wire\\:submit="cancel(${fx.requests.reqCancel})"] button[type="submit"]`);
      await browser.pause(1500);
      await a.assertNoServerError();
      await a.waitForText('Cancelled');
    });
  });

  describe('Agreement and booking module', () => {
    it('IT-08 renter accepts the agreement first; no booking yet (FR-14, FR-15)', async () => {
      await loginAs(fx.users.renter.email, 'renter');
      await browser.url(`/renter/rental-requests/${fx.requests.reqAgree}/agreement`);
      await a.scrollClick('form[wire\\:submit="acceptTerms"] input[type="checkbox"]');
      await a.submitWireForm('acceptTerms');
      await browser.pause(1500);
      await browser.url('/renter/rentals');
      assert.ok(!(await a.pageText()).includes(fx.listings.speaker.name), 'booking created after only one acceptance');
    });

    it('IT-09 owner accepts too → booking confirmed and both are notified (FR-14, FR-40)', async () => {
      await loginAs(fx.users.owner.email, 'owner');
      await browser.url(`/owner/rental-requests/${fx.requests.reqAgree}/agreement`);
      await a.scrollClick('form[wire\\:submit="acceptTerms"] input[type="checkbox"]');
      await a.submitWireForm('acceptTerms');
      await browser.pause(1500);
      await browser.url('/owner/rentals');
      await a.waitForText(fx.listings.speaker.name);
      await browser.url('/notifications');
      await a.waitForText('Booking confirmed');
    });
  });

  describe('Payments module (owner wallet)', () => {
    it('IT-10 renter sees the owner\'s GCash instructions and submits payment proof (FR-41)', async () => {
      await loginAs(fx.users.renter.email, 'renter');
      await browser.url(`/renter/rentals/${fx.rentals.pay}`);
      await a.waitForText('GCash');
      await a.type('#payment-reference', 'MOD-PAY-1');
      await a.upload('#payment-proof');
      await browser.pause(2000);
      await a.submitWireForm('submitPaymentProof');
      await a.waitForText('Payment proof submitted');
    });

    it('IT-11 owner rejects the proof with a reason (FR-73)', async () => {
      await loginAs(fx.users.owner.email, 'owner');
      await browser.url(`/owner/rentals/${fx.rentals.pay}`);
      await a.type('[wire\\:model="payment_rejection_reason"]', 'QA: receipt is blurry, please upload a clearer copy.');
      await a.autoConfirm();
      await a.scrollClick('form[wire\\:submit^="rejectPaymentProof("] button[type="submit"]');
      await browser.pause(500);
      await a.acceptDialogIfOpen();
      await browser.pause(1500);
      await a.assertNoServerError();
    });

    it('IT-12 renter sees the rejection reason and resubmits corrected proof (FR-73)', async () => {
      await loginAs(fx.users.renter.email, 'renter');
      await browser.url(`/renter/rentals/${fx.rentals.pay}`);
      await a.waitForText('QA: receipt is blurry');
      await a.type('#payment-reference', 'MOD-PAY-2');
      await a.upload('#payment-proof');
      await browser.pause(2000);
      await a.submitWireForm('submitPaymentProof');
      await a.waitForText('Payment proof submitted');
    });

    it('IT-13 owner confirms the funds were received → booking Paid, no admin needed (FR-73)', async () => {
      await loginAs(fx.users.owner.email, 'owner');
      await browser.url(`/owner/rentals/${fx.rentals.pay}`);
      await a.scrollClick('input[type="checkbox"][wire\\:model="owner_funds_received"]');
      await a.confirmDialogAction('confirmPaymentReceipt(');
      await a.waitForText('Payment confirmed.');
    });

    it('IT-14 the paid item\'s status badge shows Reserved (FR-42)', async () => {
      await freshSession();
      await browser.url(`/listings/${fx.listings.sony.id}`);
      // Read the availability badge in the Rental rates panel, not any "Reserved" text on the page.
      await browser.waitUntil(async () => (await browser.execute(() => {
        const panel = document.querySelector('#rental-rates-title')?.closest('section');
        return panel ? panel.querySelector('div.flex')?.innerText || panel.innerText.slice(0, 80) : '';
      })).includes('Reserved'), { timeout: 15000, timeoutMsg: 'availability badge is not Reserved' });
    });

    it('IT-15 both parties see the transaction receipt with the reference (FR-17)', async () => {
      await loginAs(fx.users.renter.email, 'renter');
      await browser.url(`/renter/rentals/${fx.rentals.pay}`);
      await a.waitForText('Transaction receipt');
      await a.waitForText('MOD-PAY-2');
      await loginAs(fx.users.owner.email, 'owner');
      await browser.url(`/owner/rentals/${fx.rentals.pay}`);
      await a.waitForText('Transaction receipt');
    });
  });

  describe('Cancellation module', () => {
    it('IT-16 cancelling within the 48-hour window needs a reason and goes to owner review (FR-84)', async () => {
      await loginAs(fx.users.renter.email, 'renter');
      await browser.url(`/renter/rentals/${fx.rentals.cancelFee}`);
      await a.clickWire("$toggle('showCancelForm')");
      await a.type('#cancellation_reason', 'QA: no longer needed.');
      await a.clickWire('cancelRental');
      await a.waitForText('Cancellation request: pending');
      await a.waitForText('Cancellation fee if approved: ₱240.00');
    });

    it('IT-17 owner approves the cancellation → booking cancelled with the ₱240 fee (FR-35, FR-84)', async () => {
      await loginAs(fx.users.owner.email, 'owner');
      await browser.url(`/owner/rentals/${fx.rentals.cancelFee}`);
      await a.type('#cancellation-review-notes', 'QA: approved, item not yet packed.');
      await a.submitWireForm('reviewCancellation(true)');
      await a.waitForText('Cancellation fee: ₱240.00');
    });

    it('IT-18 cancelling 10 days ahead takes effect at once with no fee (FR-34, FR-35)', async () => {
      await loginAs(fx.users.renter.email, 'renter');
      await browser.url(`/renter/rentals/${fx.rentals.cancelFree}`);
      await a.clickWire("$toggle('showCancelForm')");
      await a.type('#cancellation_reason', 'QA: plans changed.');
      await a.clickWire('cancelRental');
      await a.waitForText('This booking was cancelled.');
      await a.waitForText('Cancellation fee: ₱0.00');
    });

    it('IT-19 the owner is notified and the cancelled dates can be requested again (FR-36)', async () => {
      await loginAs(fx.users.owner.email, 'owner');
      await browser.url('/notifications');
      await a.waitForText('Rental cancelled');
      await loginAs(fx.users.renter2.email, 'renter');
      await browser.url(`/listings/${fx.listings.kayak.id}`);
      await a.scrollClick('button[aria-controls="rental-request-modal"]');
      await a.setField('#start_date', a.daysFromNow(10));
      await browser.pause(600);
      await a.setField('#end_date', a.daysFromNow(11));
      await browser.pause(600);
      await a.scrollClick('#accept_terms');
      await a.submitWireForm('submit');
      await a.waitForText('Rental request sent');
    });

    it('IT-20 history keeps the reason, fee and refund to arrange: ₱2,320 paid − ₱240 = ₱2,080 (FR-37)', async () => {
      await loginAs(fx.users.renter.email, 'renter');
      await browser.url(`/renter/rentals/${fx.rentals.cancelFee}`);
      await a.waitForText('QA: no longer needed.');
      await a.waitForText('Cancellation fee: ₱240.00');
      await a.waitForText('Refund to arrange: ₱2,080.00');
    });
  });

  describe('Return, damage and dispute module', () => {
    it('IT-21 renter confirms the return of an active rental (FR-78)', async () => {
      await browser.url(`/renter/rentals/${fx.rentals.return}`);
      await a.clickWire('confirmReturn');
      await browser.pause(1500);
      assert.ok(!(await $('[wire\\:click="confirmReturn"]').isExisting()), 'return button still shown');
    });

    it('IT-22 owner records damage (₱300) and closes the return (FR-21, FR-80)', async () => {
      await loginAs(fx.users.owner.email, 'owner');
      await browser.url(`/owner/rentals/${fx.rentals.return}`);
      await a.type('#after_notes', 'QA: crack on the body.');
      await a.scrollClick('input[type="checkbox"][wire\\:model\\.live="after_has_damage"]');
      await a.type('#damage_type', 'Cracked body');
      await a.type('#damage_description', 'QA: 3 cm crack near the sound hole.');
      await a.type('#damage_estimated_cost', '300');
      await a.submitWireForm('recordAfterCondition');
      await a.waitForText('QA: crack on the body.');
      await a.clickWire('confirmReturn');
      await a.waitForText('Completed');
    });

    it('IT-23 renter disputes the damage claim (FR-80, FR-29)', async () => {
      await loginAs(fx.users.renter.email, 'renter');
      await browser.url(`/renter/rentals/${fx.rentals.return}`);
      await a.type('#damage_response_notes', 'QA: the crack was there before pickup.');
      await a.clickWire('disputeDamageClaim');
      await browser.pause(1500);
      await a.assertNoServerError();
      await a.waitForText('You disputed this claim.');
    });

    it('IT-24 admin resolves the dispute with ₱150 partial compensation (FR-30, FR-46)', async () => {
      await asAdmin();
      await browser.url('/admin/disputes');
      await a.scrollClick('[wire\\:click^="startResolving("]');
      await a.setField('#resolution', 'partial_compensation');
      await browser.pause(1000);
      await a.type('#partial_amount', '150');
      await a.type('#resolution_notes', 'QA: shared responsibility.');
      await a.submitWireForm('confirmResolve');
      await browser.pause(1500);
      await a.assertNoServerError();
      await loginAs(fx.users.renter.email, 'renter');
      await browser.url('/notifications');
      await a.waitForText('Dispute resolved');
    });
  });

  describe('Messaging module', () => {
    it('IT-25 renter messages the owner from a listing (FR-31)', async () => {
      await browser.url(`/listings/${fx.listings.tripod.id}/message`);
      await a.type('form[wire\\:submit="send"] [wire\\:model="body"]', 'QA: is the tripod still available this weekend?');
      await a.submitWireForm('send');
      await a.waitForText('QA: is the tripod still available this weekend?');
    });

    it('IT-26 owner sees the message in the inbox and replies (FR-31, FR-32)', async () => {
      await loginAs(fx.users.owner.email, 'owner');
      await browser.url('/messages');
      await a.waitForText(fx.users.renter.name);
      const href = await browser.execute(() => {
        const link = Array.from(document.querySelectorAll('a[href*="conversation"], a[href*="/messages/"], a[href*="listing-conversations"]'))[0];
        return link && link.getAttribute('href');
      });
      assert.ok(href, 'no conversation link in the inbox');
      await browser.url(href);
      await a.type('form[wire\\:submit="send"] [wire\\:model="body"]', 'QA: yes, it is available.');
      await a.submitWireForm('send');
      await a.waitForText('QA: yes, it is available.');
    });
  });

  describe('Admin moderation module', () => {
    it('IT-27 admin removes a live listing; it leaves the marketplace (FR-27)', async () => {
      await asAdmin();
      // The list shows 10 per page; find the page that holds the listing.
      for (let page = 1; page <= 5; page++) {
        await browser.url(`/admin/listings?page=${page}`);
        if (await $(`[wire\\:click="remove(${fx.listings.suspicious.id})"]`).isExisting()) break;
      }
      await a.clickWire(`remove(${fx.listings.suspicious.id})`);
      await browser.pause(1500);
      await a.assertNoServerError();
      await freshSession();
      await browser.url('/listings');
      await a.type('input[aria-label="Search items"]', fx.listings.suspicious.name);
      await a.waitForText('No listings match your filters');
    });

    it('IT-28 admin suspends an account and that user can no longer log in (FR-26)', async () => {
      await asAdmin();
      await browser.url('/admin/dashboard');
      await a.clickWire(`toggleSuspension(${fx.users.suspend.id})`);
      await a.waitForText('Suspended');
      await LoginPage.open();
      await LoginPage.loginAs(fx.users.suspend.email, 'password');
      await a.waitForText('Your account has been suspended.');
    });
  });
});
