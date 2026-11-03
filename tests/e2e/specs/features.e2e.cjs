// Integration tests for the processes added in Requirements v2: password recovery and lockout,
// interface switching, identity review and gating, rental cart, availability waitlist, pickup
// scheduling, history and earnings, and the map. Uses fixtures from tests/e2e/fixtures/seed.php.
const assert = require('assert');
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const a = require('../support/actions.cjs');
const { fixtures, freshSession, loginAs, asAdmin } = require('../support/session.cjs');
const LoginPage = require('../pages/LoginPage.cjs');

let fx;
const NEW_PASSWORD = 'Qa-Reset!2026';

// Login without asserting the landing page (unverified members may be steered to ID review).
async function loginLoose(email, iface, password = 'password') {
  await LoginPage.open();
  await LoginPage.loginAs(email, password);
  await a.scrollClick(`[wire\\:click="chooseInterface('${iface}')"]`);
  await browser.pause(1500);
  await a.assertNoServerError();
}

function latestResetCode() {
  const log = fs.readFileSync(path.join(__dirname, '..', '..', '..', 'storage', 'logs', 'laravel.log'), 'utf8');
  const codes = log.match(/Use this code to reset your password:\s*(\d{6})/g);
  if (!codes) throw new Error('No reset code found in laravel.log');
  return codes[codes.length - 1].match(/(\d{6})/)[1];
}

describe('Integration tests — Requirements v2 processes', () => {
  before(() => {
    fx = fixtures();
  });

  describe('Account security', () => {
    it('IT-29 password reset with the emailed six-digit code, then login with the new password (FR-04)', async () => {
      await freshSession();
      await browser.url('/forgot-password');
      await a.type('#email', fx.users.reset.email);
      await a.submitWireForm('sendPasswordResetCode');
      await a.waitForUrl(/reset-password/);
      await a.waitForText('a reset code has been sent');
      await browser.pause(500);
      await a.type('#code', latestResetCode());
      await a.type('#password', NEW_PASSWORD);
      await a.type('#password_confirmation', NEW_PASSWORD);
      await a.submitWireForm('resetPassword');
      await a.waitForText('Your password has been reset.');
      await loginAs(fx.users.reset.email, 'renter', NEW_PASSWORD);
    });

    it('IT-30 three wrong passwords lock the account (FR-57)', async () => {
      for (let i = 0; i < 3; i++) {
        await LoginPage.open();
        await LoginPage.loginAs(fx.users.lockout2.email, `wrong-${i}`);
        await browser.pause(800);
      }
      await LoginPage.open();
      await LoginPage.loginAs(fx.users.lockout2.email, 'password');
      await a.waitForText('Too many login attempts');
    });

    it('IT-31 member switches from Renter to Owner without logging out (FR-54)', async () => {
      await loginAs(fx.users.renter.email, 'renter');
      await a.scrollClick('form[action$="/interface"] button[type="submit"]');
      await a.waitForUrl(/\/owner\/dashboard/);
      await a.waitForText('Owner dashboard');
    });
  });

  describe('Identity review', () => {
    it('IT-32 a member without approved ID cannot publish a listing (FR-61)', async () => {
      await loginLoose(fx.users.newbie.email, 'owner');
      await browser.url('/owner/listings/create');
      const firstCategory = await browser.execute(() => {
        const opt = Array.from(document.querySelectorAll('#category_id option')).find((o) => o.value && o.value !== 'other');
        return opt && opt.value;
      });
      await a.setField('#category_id', firstCategory);
      await browser.pause(1200);
      await a.type('#name', 'QA Newbie Item');
      await a.type('#description', 'Should be blocked until the ID review is approved.');
      await a.type('#location', 'Nasugbu, Batangas');
      await a.setField('#available_from', a.daysFromNow(0));
      await a.setField('#available_until', a.daysFromNow(30));
      await a.type('#price_per_day', '300');
      const map = await $('[x-ref="map"]');
      await map.scrollIntoView({ block: 'center' });
      await browser.pause(800);
      await map.click();
      await a.upload('#listing-photos');
      await (await $('[wire\\:click^="removePendingPhoto"]')).waitForExist({ timeout: 20000 });
      await a.submitWireForm('save');
      await a.waitForText('Complete your ID review before creating or publishing a listing.');
    });

    it('IT-33 admin rejects an ID submission with a reason; the member sees it (FR-60, FR-58)', async () => {
      const php = process.platform === 'win32' ? 'php.exe' : 'php';
      const out = execFileSync(php, [path.join(__dirname, '..', 'fixtures', 'identity-pending.php'), fx.users.newbie.email], { env: process.env }).toString();
      const submission = JSON.parse(out.trim().split('\n').pop()).submission;
      await asAdmin();
      await browser.url('/admin/identity-submissions');
      await a.scrollClick(`[wire\\:click="selectSubmission(${submission})"]`);
      await a.type('#identity-review-notes', 'QA: ID photo is blurry; please retake it in good light.');
      await a.clickWire("review('rejected')");
      await a.waitForText('Identity review saved.');
      await loginLoose(fx.users.newbie.email, 'renter');
      await browser.url('/identity/verify');
      await a.waitForText('QA: ID photo is blurry');
    });

    it('IT-34 changing the profile name clears ID approval (FR-62, FR-38)', async () => {
      await loginAs(fx.users.profile.email, 'renter');
      await browser.url('/profile');
      await a.type('#name', 'Fixture Profile Renamed');
      await a.submitWireForm('updateProfileInformation');
      await browser.pause(2000);
      await browser.url('/identity/verify');
      await (await $('#identity-form-title')).waitForExist({ timeout: 15000 });
    });
  });

  describe('Rental cart and waitlist', () => {
    it('IT-35 renter saves a draft to the cart; no dates are reserved (FR-68)', async () => {
      await loginAs(fx.users.renter2.email, 'renter');
      await browser.url(`/listings/${fx.listings.tent.id}`);
      await a.scrollClick('button[aria-controls="rental-request-modal"]');
      await a.setField('#start_date', a.daysFromNow(40));
      await browser.pause(600);
      await a.setField('#end_date', a.daysFromNow(41));
      await browser.pause(600);
      await a.clickWire('addToCart');
      await a.waitForUrl(/\/renter\/cart/);
      await a.waitForText('Rental draft added to your cart. No dates are reserved.');
      await a.waitForText(fx.listings.tent.name);
    });

    it('IT-36 renter submits the cart draft as a rental request (FR-69)', async () => {
      // Make sure the tent draft is selected (it may already be ticked; Select all would toggle it off).
      const box = await $('input[type="checkbox"][wire\\:model\\.live="selected"]');
      await box.waitForExist({ timeout: 15000 });
      if (!(await box.isSelected())) await a.scrollClick('input[type="checkbox"][wire\\:model\\.live="selected"]');
      await a.waitForTextI('1 item selected');
      const terms = await $('#cart-accept-terms');
      if (!(await terms.isSelected())) await a.scrollClick('#cart-accept-terms');
      await a.submitWireForm('checkout');
      await a.waitForText('Rental requests sent!');
      await browser.url('/renter/rental-requests');
      await a.waitForText(fx.listings.tent.name);
    });

    it('IT-37 renter joins the waitlist for dates that are already booked (FR-70)', async () => {
      await browser.url(`/listings/${fx.listings.drill.id}`);
      await a.scrollClick('button[aria-controls="rental-request-modal"]');
      await a.setField('#start_date', fx.dates.drillStart);
      await browser.pause(600);
      await a.setField('#end_date', fx.dates.drillEnd);
      await browser.pause(1200);
      await a.clickWire('joinWaitlist');
      await a.waitForText('Joined the waitlist');
      await browser.url('/renter/cart');
      await a.waitForText(fx.listings.drill.name);
    });
  });

  describe('Pickup or delivery scheduling', () => {
    it('IT-38 renter proposes a pickup time and place (FR-18)', async () => {
      await loginAs(fx.users.renter.email, 'renter');
      await browser.url(`/renter/rentals/${fx.rentals.schedule}`);
      await a.clickWire('startProposal');
      await a.setField('#exchange-arrangements input[type="datetime-local"]', fx.dates.drillExchange);
      await a.type('#exchange-arrangements [wire\\:model="address"]', 'QA: Nasugbu public market entrance');
      await a.type('#exchange-arrangements [wire\\:model="notes"]', 'QA: will bring a valid ID.');
      await a.submitWireForm('propose');
      await a.waitForText('QA: Nasugbu public market entrance');
    });

    it('IT-39 owner confirms the same proposal (FR-18)', async () => {
      await loginAs(fx.users.owner.email, 'owner');
      await browser.url(`/owner/rentals/${fx.rentals.schedule}`);
      await a.waitForText('QA: Nasugbu public market entrance');
      await a.scrollClick('[wire\\:click^="confirm("]');
      await browser.pause(1500);
      await a.assertNoServerError();
      assert.ok(!(await $('[wire\\:click^="confirm("]').isExisting()), 'proposal still awaiting the owner\'s confirmation');
    });
  });

  describe('History, earnings and map', () => {
    it('IT-40 renter history keeps cancelled and completed bookings (FR-24)', async () => {
      await loginAs(fx.users.renter.email, 'renter');
      await browser.url('/renter/rentals');
      await a.waitForText(fx.listings.bike.name);
      await a.waitForText('Cancelled');
      await a.waitForText(fx.listings.guitar.name);
    });

    it('IT-41 owner dashboard shows earnings with deposits kept separate (FR-25)', async () => {
      await loginAs(fx.users.owner.email, 'owner');
      await a.waitForTextI('Total earnings');
      await a.waitForTextI('Excludes deposits and processing fees');
      await a.waitForText(fx.listings.guitar.name);
    });

    it('IT-42 map shows listing pins and the keyword filter narrows them (FR-48, FR-52)', async () => {
      await freshSession();
      await browser.url('/map');
      await browser.waitUntil(async () => (await $$('.leaflet-marker-icon')).length > 0, { timeout: 20000, timeoutMsg: 'no pins on the map' });
      const before = (await $$('.leaflet-marker-icon')).length;
      await a.type('input[wire\\:model\\.live\\.debounce\\.400ms="keyword"]', fx.listings.tripod.name);
      await browser.waitUntil(async () => {
        const n = (await $$('.leaflet-marker-icon')).length;
        return n > 0 && n < before;
      }, { timeout: 20000, timeoutMsg: `keyword did not narrow the pins (was ${before})` });
    });
  });
});
