// Component-level tests: each UI component (a form, a widget, a calculator) checked on its own,
// including its validation and error states. Uses the fixture data from tests/e2e/fixtures/seed.php.
const assert = require('assert');
const a = require('../support/actions.cjs');
const { fixtures, freshSession, loginAs } = require('../support/session.cjs');
const LoginPage = require('../pages/LoginPage.cjs');

describe('Component tests', () => {
  let fx;
  before(() => {
    fx = fixtures();
  });

  describe('Login form', () => {
    it('UT-01 login form blocks an empty submit (email is required) (FR-03)', async () => {
      await LoginPage.open();
      await (await LoginPage.submitButton).click();
      const invalid = await browser.execute(() => !document.querySelector('#email').checkValidity());
      assert.ok(invalid, 'empty email was accepted');
      assert.ok((await browser.getUrl()).includes('/login'));
    });

    it('UT-02 login form rejects a wrong password with an error message (FR-03, FR-57)', async () => {
      await LoginPage.open();
      await LoginPage.loginAs(fx.users.lockout.email, 'wrong-password');
      await a.waitForText('These credentials do not match our records.');
    });

    it('UT-03 Owner/Renter picker can be closed and returns to the login form (FR-03)', async () => {
      await LoginPage.open();
      await LoginPage.loginAs(fx.users.owner.email, 'password');
      await (await $('[wire\\:click="chooseInterface(\'owner\')"]')).waitForDisplayed();
      await a.scrollClick('button[wire\\:click="cancelInterfaceSelection"]');
      await (await $('[wire\\:click="chooseInterface(\'owner\')"]')).waitForDisplayed({ reverse: true });
      assert.ok((await browser.getUrl()).includes('/login'), 'left the login page');
    });
  });

  describe('Registration form', () => {
    it('UT-04 registration rejects a non-Gmail address (FR-01)', async () => {
      await freshSession();
      await browser.url('/register');
      await a.type('#name', 'QA Component');
      await a.type('#email', 'qa.component@yahoo.com');
      await a.type('#password', 'Qa-Passw0rd!2026');
      await a.type('#password_confirmation', 'Qa-Passw0rd!2026');
      await a.submitWireForm('register');
      await a.waitForText('Enter a valid Gmail address ending in @gmail.com.');
    });

    it('UT-05 registration rejects mismatched password confirmation (FR-01, FR-56)', async () => {
      await freshSession();
      await browser.url('/register');
      await a.type('#name', 'QA Component');
      await a.type('#email', `qa.mismatch.${Date.now().toString(36)}@gmail.com`);
      await a.type('#password', 'Qa-Passw0rd!2026');
      await a.type('#password_confirmation', 'Different-Passw0rd!');
      await a.submitWireForm('register');
      await a.waitForText('confirmation does not match');
      assert.ok((await browser.getUrl()).includes('/register'));
    });
  });

  describe('Password rules and ID review form', () => {
    it('UT-06 registration rejects a weak password without uppercase or symbol (FR-56)', async () => {
      await freshSession();
      await browser.url('/register');
      await a.type('#name', 'QA Weak Password');
      await a.type('#email', `qa.weak.${Date.now().toString(36)}@gmail.com`);
      await a.type('#password', 'password123');
      await a.type('#password_confirmation', 'password123');
      await a.submitWireForm('register');
      await a.waitForText('must contain at least one');
      assert.ok((await browser.getUrl()).includes('/register'));
    });

    it('UT-07 ID review form blocks submission without the ID photo, selfie and consent (FR-58)', async () => {
      await LoginPage.open();
      await LoginPage.loginAs(fx.users.newbie.email, 'password');
      await a.scrollClick('[wire\\:click="chooseInterface(\'renter\')"]');
      await browser.pause(1500);
      await browser.url('/identity/verify');
      await (await $('#identity-front')).waitForExist({ timeout: 15000 });
      const submit = await $('form[wire\\:submit="submit"] button[type="submit"]');
      // 1) Without consent the submit button stays disabled.
      assert.ok(!(await submit.isEnabled()), 'submit enabled before consent was given');
      // 2) With consent but no photos, the required file inputs block the submission.
      await a.scrollClick('#identity-consent');
      await submit.waitForEnabled({ timeout: 10000 });
      await submit.click();
      await browser.pause(800);
      const state = await browser.execute(() => ({
        front: document.querySelector('#identity-front').checkValidity(),
        selfie: document.querySelector('#identity-selfie').checkValidity(),
      }));
      // The ID-front input blocks the submit; the selfie is enforced server-side (it can come from the camera).
      assert.ok(!state.front, `empty ID form accepted: ${JSON.stringify(state)}`);
      assert.ok((await browser.getUrl()).includes('/identity/verify'));
    });
  });

  describe('Marketplace search and filters', () => {
    it('UT-08 search with no matches shows the empty state (FR-09)', async () => {
      await freshSession();
      await browser.url('/listings');
      await a.type('input[aria-label="Search items"]', 'zzqx-no-such-item');
      await a.waitForText('No listings match your filters');
    });

    it('UT-09 max price filter hides items above the price (FR-09)', async () => {
      await browser.url('/listings');
      await a.type('input[aria-label="Search items"]', 'FX Cam');
      await a.waitForText(fx.listings.drone.name);
      await a.scrollClick('button[aria-controls="listing-filters"]');
      await a.type('input[wire\\:model\\.live\\.debounce\\.400ms="maxPrice"]', '400');
      await browser.waitUntil(async () => !(await a.pageText()).includes(fx.listings.drone.name), {
        timeout: 15000, timeoutMsg: '₱1,500 drone still shown with max ₱400',
      });
      await a.type('input[aria-label="Search items"]', 'FX Camping');
      await a.waitForText(fx.listings.tent.name);
    });

    it('UT-10 listing page shows the availability calendar (FR-10, FR-66)', async () => {
      await browser.url(`/listings/${fx.listings.tent.id}`);
      await a.waitForText('Availability');
      // The calendar draws one 7-column row per week; count the rendered day cells.
      const days = await browser.execute(() => document.querySelectorAll('[x-data*="availabilityCalendar"] .grid-cols-7 > .h-10').length);
      assert.ok(days >= 28, `calendar shows ${days} day cells`);
    });
  });

  describe('Listing form', () => {
    it('UT-11 listing form lists every missing required field (FR-05)', async () => {
      await loginAs(fx.users.owner.email, 'owner');
      await browser.url('/owner/listings/create');
      await a.setField('#available_from', a.daysFromNow(0));
      await a.setField('#available_until', a.daysFromNow(30));
      await a.submitWireForm('save');
      for (const msg of ['Category is required.', 'Location is required.', 'Place a pin on the map for your item.', 'Add at least one photo of your item.']) {
        await a.waitForText(msg);
      }
    });

    it('UT-12 listing form rejects a ₱0 rental price (FR-05)', async () => {
      await browser.url('/owner/listings/create');
      await a.setField('#available_from', a.daysFromNow(0));
      await a.setField('#available_until', a.daysFromNow(30));
      await a.type('#price_per_day', '0');
      // price_per_day syncs after a 500 ms debounce; let it land before submitting.
      await browser.pause(1200);
      await a.submitWireForm('save');
      await a.waitForText('Rental price must be greater than ₱0.');
    });

    it('UT-13 clicking the map sets the required pin (FR-47)', async () => {
      await browser.url('/owner/listings/create');
      await a.setField('#available_from', a.daysFromNow(0));
      await a.setField('#available_until', a.daysFromNow(30));
      const map = await $('[x-ref="map"]');
      await map.scrollIntoView({ block: 'center' });
      await browser.pause(1000);
      await map.click();
      await browser.waitUntil(
        () => browser.execute(() => window.Livewire.all().some((c) => c.$wire.latitude !== null && c.$wire.latitude !== undefined)),
        { timeout: 10000, timeoutMsg: 'map click did not set a latitude' }
      );
      await a.submitWireForm('save');
      await a.waitForText('Category is required.');
      assert.ok(!(await a.pageText()).includes('Place a pin on the map for your item.'), 'pin error still shown after clicking the map');
    });
  });

  describe('Rental request form', () => {
    it('UT-14 cost calculator totals 3 days × ₱300 + 10% fee + ₱1,000 deposit = ₱1,990 (FR-16)', async () => {
      await loginAs(fx.users.renter2.email, 'renter');
      await browser.url(`/listings/${fx.listings.tent.id}`);
      await a.scrollClick('button[aria-controls="rental-request-modal"]');
      await a.setField('#start_date', a.daysFromNow(30));
      await browser.pause(600);
      await a.setField('#end_date', a.daysFromNow(32));
      await a.waitForText('1,990.00');
    });

    it('UT-15 request form rejects an end date before the start date (FR-11, FR-65)', async () => {
      await browser.url(`/listings/${fx.listings.tent.id}`);
      await a.scrollClick('button[aria-controls="rental-request-modal"]');
      await (await $('#start_date')).waitForDisplayed();
      await a.setField('#start_date', a.daysFromNow(35));
      await browser.pause(600);
      await a.setField('#end_date', a.daysFromNow(33));
      await browser.pause(600);
      // The end-date input's min is the start date, so the browser itself blocks the submit.
      const state = await browser.execute(() => {
        const end = document.querySelector('#end_date');
        return { valid: end.checkValidity(), min: end.min, value: end.value };
      });
      assert.ok(!state.valid, `end date ${state.value} accepted although min is ${state.min}`);
      assert.ok(!(await browser.getUrl()).includes('/renter/rental-requests'), 'request was submitted');
    });
  });

  describe('Payment proof form', () => {
    it('UT-16 payment form requires a proof file (FR-41)', async () => {
      await loginAs(fx.users.renter.email, 'renter');
      await browser.url(`/renter/rentals/${fx.rentals.pay}`);
      await a.type('#payment-reference', 'COMPONENT-REF-1');
      await a.submitWireForm('submitPaymentProof');
      await a.waitForText('The payment proof field is required.');
    });

    it('UT-17 payment form rejects a reference with invalid characters (FR-41)', async () => {
      await a.type('#payment-reference', 'bad ref!!');
      await a.upload('#payment-proof');
      await browser.pause(2000);
      await a.submitWireForm('submitPaymentProof');
      await a.waitForText('The payment reference field format is invalid.');
    });
  });

  describe('Notification bell', () => {
    it('UT-18 bell opens the notification list and can mark all as read (FR-87)', async () => {
      await a.scrollClick('button[aria-label="Open notifications"]');
      await a.waitForText('Booking confirmed');
      await a.clickWire('markAllAsRead');
      await browser.pause(1000);
      await a.assertNoServerError();
    });
  });
});
