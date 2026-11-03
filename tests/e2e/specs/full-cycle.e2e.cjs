// System test: one complete rental through the real UI, following the Requirements v2 process flow:
// register → email confirmation → identity review (admin) → owner wallet QR → publish → browse
// → request → send for agreement → both accept → payment proof → owner confirms receipt
// → before-condition + handover (both) → return (both) + inspection → deposit release + renter receipt
// → reviews → notifications → owner reopens the item.
// Steps share state and run in order; once one fails the rest are skipped.
const assert = require('assert');
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const LoginPage = require('../pages/LoginPage.cjs');
const a = require('../support/actions.cjs');

const RUN = Date.now().toString(36);
const PASSWORD = 'Qa-Passw0rd!2026';
const owner = { name: `QA Owner ${RUN}`, email: `qa.owner.${RUN}@gmail.com` };
const renter = { name: `QA Renter ${RUN}`, email: `qa.renter.${RUN}@gmail.com` };
const ITEM = `QA Camera ${RUN}`;
const state = {};
const results = [];
let broken = false;

async function freshSession() {
  // Cookies can only be cleared for the domain currently loaded.
  await browser.url('/');
  await browser.deleteCookies();
  await browser.url('/');
}

async function loginAs(email, password, iface) {
  await freshSession();
  await LoginPage.open();
  await LoginPage.loginAs(email, password);
  if (iface) {
    await a.scrollClick(`[wire\\:click="chooseInterface('${iface}')"]`);
    await a.waitForUrl(new RegExp(`/${iface}/dashboard`));
  } else {
    await a.waitForUrl(/\/(admin\/)?dashboard/);
  }
  await a.assertNoServerError();
}

async function register(user) {
  await freshSession();
  await browser.url('/register');
  await a.type('#name', user.name);
  await a.type('#email', user.email);
  // The password fields block paste, so setValue types them key by key.
  await a.type('#password', PASSWORD);
  await a.type('#password_confirmation', PASSWORD);
  await a.submitWireForm('register');
  await a.waitForUrl(/verify-email/);
  await browser.url(a.latestVerificationLink());
  // After email confirmation, members without an approved ID are sent to identity review.
  await a.waitForUrl(/dashboard|identity/);
  await a.assertNoServerError();
}

// Stands in for the member's ID + selfie upload and the local face comparison (FR-58/59),
// which need real face photos and a Python face model; the admin review below is real (FR-60).
function submitIdentityFor(user) {
  const php = process.platform === 'win32' ? 'php.exe' : 'php';
  const out = execFileSync(php, [path.join(__dirname, '..', 'fixtures', 'identity-pending.php'), user.email], { env: process.env }).toString();
  return JSON.parse(out.trim().split('\n').pop()).submission;
}

async function approveIdentity(submissionId) {
  await a.scrollClick(`[wire\\:click="selectSubmission(${submissionId})"]`);
  for (const model of ['qrChecked', 'detailsMatch', 'faceReviewed']) {
    const box = await $(`input[type="checkbox"][wire\\:model="${model}"]`);
    await box.waitForExist({ timeout: 15000 });
    if (!(await box.isSelected())) await a.scrollClick(`input[type="checkbox"][wire\\:model="${model}"]`);
  }
  await a.type('#identity-review-notes', 'QA: National ID QR checked; details and face match.');
  await a.submitWireForm("review('approved')");
  await a.waitForText('Identity review saved.');
}

describe('System test: full rental cycle (Requirements v2 process flow)', function () {
  before(() => a.ensureFixture());

  beforeEach(function () {
    if (broken) this.skip();
  });

  afterEach(async function () {
    const entry = { step: this.currentTest.title, state: this.currentTest.state, ms: this.currentTest.duration };
    if (this.currentTest.state === 'failed') {
      broken = true;
      entry.error = String(this.currentTest.err && this.currentTest.err.message).slice(0, 500);
      entry.url = await browser.getUrl().catch(() => '');
    }
    results.push(entry);
  });

  after(() => {
    const out = path.join(__dirname, '..', 'artifacts', 'full-cycle-results.json');
    fs.writeFileSync(out, JSON.stringify({ run: RUN, owner, renter, item: ITEM, state, results }, null, 2));
  });

  it('ST-01 owner registers with Gmail and confirms the email link (FR-01, FR-02)', async () => {
    await register(owner);
  });

  it('ST-02 renter registers with Gmail and confirms the email link (FR-01, FR-02)', async () => {
    await register(renter);
  });

  it('ST-03 admin approves both members\' identity reviews (FR-39, FR-60, FR-61)', async () => {
    const ownerSubmission = submitIdentityFor(owner);
    const renterSubmission = submitIdentityFor(renter);
    await loginAs('admin@tala.test', 'password');
    await browser.url('/admin/identity-submissions');
    await approveIdentity(ownerSubmission);
    await browser.url('/admin/identity-submissions');
    await approveIdentity(renterSubmission);
  });

  it('ST-04 owner sets up a GCash payment QR (FR-72)', async () => {
    await loginAs(owner.email, PASSWORD, 'owner');
    await browser.url('/owner/payments');
    await a.setField('#owner-payment-provider', 'gcash');
    await a.type('#owner-payment-account-name', owner.name);
    await a.type('#owner-payment-instructions', 'QA: scan the GCash QR and pay the full amount.');
    await a.upload('#owner-payment-qr');
    await browser.pause(2000);
    const enabled = await $('input[type="checkbox"][wire\\:model="enabled"]');
    if (!(await enabled.isSelected())) await a.scrollClick('input[type="checkbox"][wire\\:model="enabled"]');
    await a.submitWireForm('save');
    await a.waitForText('Payment QR settings saved.');
  });

  it('ST-05 owner publishes a listing with photo, map pin and rates (FR-05, FR-06, FR-47)', async () => {
    await browser.url('/owner/listings/create');
    await a.assertNoServerError();
    const firstCategory = await browser.execute(() => {
      const opt = Array.from(document.querySelectorAll('#category_id option')).find((o) => o.value && o.value !== 'other');
      return opt && opt.value;
    });
    await a.setField('#category_id', firstCategory);
    await browser.pause(1200);
    await a.type('#name', ITEM);
    await a.type('#brand', 'Canon');
    await a.type('#model', 'EOS M50');
    await a.type('#description', 'Mirrorless camera with 15-45mm kit lens, 2 batteries, charger and bag. QA run item.');
    await a.type('#location', 'Nasugbu, Batangas');
    await a.type('#max_rental_duration_days', '7');
    await a.setField('#available_from', a.daysFromNow(0));
    await a.setField('#available_until', a.daysFromNow(30));
    await a.type('#rental_rules', 'Return clean. No use in rain.');
    await a.type('#price_per_day', '500');
    await a.type('#security_deposit', '1000');
    const map = await $('[x-ref="map"]');
    await map.scrollIntoView({ block: 'center' });
    await browser.pause(800);
    await map.click();
    await browser.pause(800);
    await a.upload('#listing-photos');
    await (await $('[wire\\:click^="removePendingPhoto"]')).waitForExist({ timeout: 20000 });
    await a.submitWireForm('save');
    await a.waitForUrl(/\/owner\/listings(\?|$)/);
    await a.waitForText('Listing published.');
  });

  it('ST-06 listing is found by search and its details page opens (FR-09, FR-10)', async () => {
    await freshSession();
    await browser.url('/listings');
    await a.type('input[aria-label="Search items"]', ITEM);
    await a.waitForText(ITEM);
    const href = await browser.execute((item) => {
      const link = Array.from(document.querySelectorAll('a[href*="/listings/"]')).find((el) => el.innerText.includes(item));
      return link && link.getAttribute('href');
    }, ITEM);
    assert.ok(href, 'listing card link not found');
    state.listingId = Number(href.match(/\/listings\/(\d+)/)[1]);
    await browser.url(`/listings/${state.listingId}`);
    await a.waitForText(ITEM);
    await a.waitForText('Availability');
    const ownerHref = await browser.execute(() => document.querySelector('a[href*="/users/"]')?.getAttribute('href'));
    assert.ok(ownerHref, 'no link to the owner public profile');
    state.ownerId = Number(ownerHref.match(/\/users\/(\d+)/)[1]);
  });

  it('ST-07 renter requests 3 days; quote shows ₱1,500 rental fee (FR-11, FR-16)', async () => {
    await loginAs(renter.email, PASSWORD, 'renter');
    await browser.url(`/listings/${state.listingId}`);
    await a.scrollClick('button[aria-controls="rental-request-modal"]');
    await (await $('#start_date')).waitForDisplayed({ timeout: 15000 });
    await a.setField('#start_date', a.daysFromNow(2));
    await browser.pause(800);
    await a.setField('#end_date', a.daysFromNow(4));
    await browser.pause(800);
    await a.waitForText('1,500.00');
    await a.scrollClick('#accept_terms');
    await a.submitWireForm('submit');
    await a.waitForUrl(/\/renter\/rental-requests/);
    await a.waitForText('Rental request sent');
  });

  it('ST-08 owner sends the request for agreement (FR-12)', async () => {
    await loginAs(owner.email, PASSWORD, 'owner');
    await browser.url('/owner/rental-requests');
    await a.waitForText(ITEM);
    await a.scrollClick('[wire\\:click^="startApproving("]');
    await a.scrollClick('[wire\\:click^="approve("]');
    const url = await a.waitForUrl(/\/owner\/rental-requests\/\d+\/agreement/);
    state.requestId = Number(url.match(/rental-requests\/(\d+)/)[1]);
  });

  it('ST-09 owner accepts the saved rental agreement (FR-15)', async () => {
    await a.scrollClick('form[wire\\:submit="acceptTerms"] input[type="checkbox"]');
    await a.submitWireForm('acceptTerms');
    await browser.pause(2000);
    await a.assertNoServerError();
  });

  it('ST-10 renter accepts → one booking in Payment Pending (FR-14, FR-15, FR-40)', async () => {
    await loginAs(renter.email, PASSWORD, 'renter');
    await browser.url(`/renter/rental-requests/${state.requestId}/agreement`);
    await a.scrollClick('form[wire\\:submit="acceptTerms"] input[type="checkbox"]');
    await a.submitWireForm('acceptTerms');
    await browser.pause(2000);
    await browser.url('/renter/rentals');
    await a.waitForText(ITEM);
    const href = await browser.execute(() => document.querySelector('a[href*="/renter/rentals/"]')?.getAttribute('href'));
    assert.ok(href, 'no booking created after both acceptances');
    state.rentalId = Number(href.match(/rentals\/(\d+)/)[1]);
    await browser.url(`/renter/rentals/${state.rentalId}`);
    await a.waitForText('Payment Pending');
  });

  it('ST-11 renter pays via the owner\'s GCash QR and submits proof (FR-41)', async () => {
    await a.waitForText('GCash');
    await a.type('#payment-reference', `QA-REF-${RUN}`);
    await a.upload('#payment-proof');
    await browser.pause(2500);
    await a.submitWireForm('submitPaymentProof');
    await a.waitForText('Payment proof submitted');
  });

  it('ST-12 owner confirms receipt → booking Paid with receipt (FR-73, FR-17, FR-42)', async () => {
    await loginAs(owner.email, PASSWORD, 'owner');
    await browser.url(`/owner/rentals/${state.rentalId}`);
    await a.scrollClick('input[type="checkbox"][wire\\:model="owner_funds_received"]');
    await a.confirmDialogAction('confirmPaymentReceipt(');
    await a.waitForText('Payment confirmed.');
    await a.waitForText('Transaction receipt');
  });

  it('ST-13 owner records the before-condition and confirms handover (FR-77, FR-43)', async () => {
    await a.type('#before_notes', 'QA: no scratches, both batteries included.');
    await a.upload('#before_photos');
    await browser.pause(2500);
    await a.submitWireForm('recordBeforeCondition');
    await a.waitForText('QA: no scratches');
    await a.clickWire('confirmPickup');
    await browser.pause(2000);
    await a.assertNoServerError();
  });

  it('ST-14 renter confirms receipt → rental Active (FR-43, FR-44)', async () => {
    await loginAs(renter.email, PASSWORD, 'renter');
    await browser.url(`/renter/rentals/${state.rentalId}`);
    await a.clickWire('confirmPickup');
    await a.waitForText('Active');
  });

  it('ST-15 renter acknowledges returning the item (FR-78)', async () => {
    await a.clickWire('confirmReturn');
    await browser.pause(2000);
    assert.ok(!(await $('[wire\\:click="confirmReturn"]').isExisting()), 'renter return button still shown');
  });

  it('ST-16 owner records condition and confirms return → Completed (FR-21, FR-22)', async () => {
    await loginAs(owner.email, PASSWORD, 'owner');
    await browser.url(`/owner/rentals/${state.rentalId}`);
    await a.type('#after_notes', 'QA: returned in same condition.');
    await a.submitWireForm('recordAfterCondition');
    await a.waitForText('QA: returned in same condition.');
    await a.clickWire('confirmReturn');
    await a.waitForText('Completed');
  });

  it('ST-17 owner records the deposit release (FR-81)', async () => {
    await a.confirmDialogAction('releaseDeposit');
    await a.waitForText('Released');
  });

  it('ST-18 renter confirms receiving the deposit back (FR-82)', async () => {
    await loginAs(renter.email, PASSWORD, 'renter');
    await browser.url(`/renter/rentals/${state.rentalId}`);
    await a.confirmDialogAction('confirmDepositReceipt');
    await browser.pause(1500);
    await a.assertNoServerError();
    assert.ok(!(await $('dialog [wire\\:click="confirmDepositReceipt"]').isExisting()), 'deposit receipt still awaiting confirmation');
  });

  it('ST-19 renter reviews the owner and the item (FR-23, FR-83)', async () => {
    await a.setField('#owner_rating', '4');
    await a.type('#owner_comment', 'QA: owner was responsive.');
    await a.submitWireForm('submitOwnerReview');
    await a.waitForText('QA: owner was responsive.');
    await a.setField('#listing_rating', '5');
    await a.type('#listing_comment', 'QA: camera worked perfectly.');
    await a.submitWireForm('submitListingReview');
    await a.waitForText('QA: camera worked perfectly.');
  });

  it('ST-20 owner reviews the renter (FR-23)', async () => {
    await loginAs(owner.email, PASSWORD, 'owner');
    await browser.url(`/owner/rentals/${state.rentalId}`);
    await a.setField('#renter_rating', '5');
    await a.type('#renter_comment', 'QA: on time and careful with the item.');
    await a.submitWireForm('submitRenterReview');
    await a.waitForText('QA: on time and careful');
  });

  it('ST-21 reviews appear on the owner\'s public profile and the item page (FR-45, FR-83)', async () => {
    await freshSession();
    await browser.url(`/users/${state.ownerId}`);
    await a.waitForText('QA: owner was responsive.');
    await browser.url(`/listings/${state.listingId}`);
    await a.waitForText('QA: camera worked perfectly.');
  });

  it('ST-22 renter notifications cover the key events (FR-13, FR-40, FR-87)', async () => {
    await loginAs(renter.email, PASSWORD, 'renter');
    await browser.url('/notifications');
    for (const text of ['Booking confirmed', 'Rental is now active']) await a.waitForText(text);
  });

  it('ST-23 owner reopens the inspected item for new requests (FR-67, FR-08)', async () => {
    await loginAs(owner.email, PASSWORD, 'owner');
    await browser.url('/owner/listings');
    // After inspection the row shows a dedicated reopen button (first) next to the availability toggle.
    const reopen = (await $$(`[wire\\:click="setAvailability(${state.listingId}, true)"]`))[0];
    await reopen.scrollIntoView({ block: 'center' });
    await reopen.click();
    await a.waitForText('Requests reopened.');
    await freshSession();
    await browser.url(`/listings/${state.listingId}`);
    await browser.waitUntil(async () => (await browser.execute(() => {
      const panel = document.querySelector('#rental-rates-title')?.closest('section');
      return panel ? panel.innerText.slice(0, 120) : '';
    })).includes('Available'), { timeout: 15000, timeoutMsg: 'item did not become Available after reopening' });
  });
});
