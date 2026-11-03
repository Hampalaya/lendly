// Integration tests for the requirements the earlier suites did not cover: time-based rules
// (by moving rental dates in the test DB and running the scheduled jobs by hand), map location
// features (Chrome location override), and the remaining Requirements v2 processes.
// Uses fixtures from tests/e2e/fixtures/seed.php; tests/e2e/fixtures/tweak.php moves dates.
const assert = require('assert');
const path = require('path');
const { execFileSync } = require('child_process');
const a = require('../support/actions.cjs');
const { fixtures, freshSession, loginAs, asAdmin } = require('../support/session.cjs');
const LoginPage = require('../pages/LoginPage.cjs');

let fx;
const ROOT = path.join(__dirname, '..', '..', '..');
const php = process.platform === 'win32' ? 'php.exe' : 'php';
const artisan = (command) => execFileSync(php, [path.join(ROOT, 'artisan'), command], { env: process.env, cwd: ROOT }).toString();
const tweak = (rentalId, ...changes) => execFileSync(php, [path.join(__dirname, '..', 'fixtures', 'tweak.php'), String(rentalId), ...changes], { env: process.env }).toString();

// Sets the browser's reported location through ChromeDriver's Chrome DevTools endpoint and
// grants the location permission, as a user clicking "Allow" would (FR-49, NFR-17).
async function setLocation(latitude, longitude) {
  const { protocol = 'http', hostname = 'localhost', port } = browser.options;
  const base = `${protocol}://${hostname}:${port}/session/${browser.sessionId}/goog/cdp/execute`;
  const send = (cmd, params) => fetch(base, { method: 'POST', headers: { 'content-type': 'application/json' }, body: JSON.stringify({ cmd, params }) });
  await send('Browser.grantPermissions', { permissions: ['geolocation'], origin: new URL(browser.options.baseUrl).origin });
  await send('Emulation.setGeolocationOverride', { latitude, longitude, accuracy: 20 });
}

const NASUGBU = [14.0723, 120.6328];
const pins = async () => (await $$('.leaflet-marker-icon')).length;

describe('Integration tests — remaining requirements', () => {
  before(() => {
    fx = fixtures();
  });

  describe('Rental tracking (time-based)', () => {
    it('IT-43 an active rental due back tomorrow shows Due Soon (FR-19)', async () => {
      tweak(fx.rentals.track, 'start_date=-2', 'end_date=1');
      await loginAs(fx.users.renter.email, 'renter');
      await browser.url(`/renter/rentals/${fx.rentals.track}`);
      await a.waitForText('Due Soon');
    });

    it('IT-44 the daily reminder job notifies the renter the day before the return date (FR-20)', async () => {
      artisan('rentals:send-return-reminders');
      await browser.url('/notifications');
      await a.waitForText('Return due tomorrow');
    });

    it('IT-45 two days past the end date the rental turns Overdue and both parties are notified (FR-19, FR-20)', async () => {
      tweak(fx.rentals.track, 'start_date=-5', 'end_date=-2');
      artisan('rentals:check-overdue');
      await browser.url(`/renter/rentals/${fx.rentals.track}`);
      await a.waitForText('This rental is overdue by 2 days.');
      await browser.url('/notifications');
      await a.waitForText('Rental overdue');
      await loginAs(fx.users.owner.email, 'owner');
      await browser.url('/notifications');
      await a.waitForText('Rental overdue');
    });

    it('IT-46 late fee = daily rate × started overdue days (2 × ₱250 = ₱500) and stops at return (FR-79)', async () => {
      await browser.url(`/owner/rentals/${fx.rentals.track}`);
      await a.waitForText('₱500.00');
      await a.type('#after_notes', 'QA: returned 2 days late, no damage.');
      await a.submitWireForm('recordAfterCondition');
      await a.waitForText('QA: returned 2 days late');
      await a.clickWire('confirmReturn');
      await browser.pause(2000);
      // Move the dates further back: once the return is recorded, the fee must not grow.
      tweak(fx.rentals.track, 'start_date=-6', 'end_date=-3');
      await loginAs(fx.users.renter.email, 'renter');
      await browser.url(`/renter/rentals/${fx.rentals.track}`);
      // Read the receipt's "Late fee" line itself (₱750 elsewhere is the 3-day rental fee).
      const lateFee = await browser.execute(() => {
        const dt = Array.from(document.querySelectorAll('dt')).find((el) => el.textContent.trim() === 'Late fee');
        return dt?.nextElementSibling?.textContent.trim() || '';
      });
      assert.strictEqual(lateFee, '₱500.00', `late fee after return is ${lateFee || 'missing'}`);
    });

    it('IT-47 an unpaid reservation past its payment deadline expires with no fee and blocks payment (FR-74)', async () => {
      await loginAs(fx.users.renter2.email, 'renter');
      await browser.url(`/renter/rentals/${fx.rentals.expire}`);
      await a.waitForText('Pay or submit proof by');
      tweak(fx.rentals.expire, 'payment_due_at=-1h');
      artisan('rentals:process-reservations');
      await browser.refresh();
      await a.waitForText('The payment reservation expired.');
      await a.waitForText('Cancellation fee: ₱0.00');
      assert.ok(!(await $('#payment-reference').isExisting()), 'payment form still offered after expiry');
    });
  });

  describe('Map location features', () => {
    it('IT-48 "Use my location" centres the map on the renter and marks their position (FR-49)', async () => {
      await freshSession();
      await browser.url('/map');
      await setLocation(...NASUGBU);
      await browser.refresh();
      await browser.waitUntil(async () => (await pins()) > 0, { timeout: 20000, timeoutMsg: 'no pins on the map' });
      await a.scrollClick('button[aria-label="Use my location"]');
      await a.waitForText('Location set');
    });

    it('IT-49 the distance radius limits the listings shown (FR-50)', async () => {
      const all = await pins();
      await a.setField('select[aria-label="Distance from your location"]', '5');
      await browser.pause(2500);
      const near = await pins();
      assert.ok(near > 0 && near < all, `5 km radius did not narrow the pins (${all} → ${near})`);
      await a.setField('select[aria-label="Distance from your location"]', '');
      await browser.pause(2000);
    });

    it('IT-50 with a location set, results are sorted nearest to farthest (FR-51)', async () => {
      await (await $('[aria-label="Listings sorted nearest to farthest"]')).waitForExist({ timeout: 15000 });
      const km = await browser.execute(() => Array.from(document.querySelectorAll('[aria-label="Listings sorted nearest to farthest"] *'))
        .map((el) => (el.childElementCount === 0 ? el.textContent : ''))
        .map((t) => (t.match(/([\d.,]+)\s*km/) || [])[1]).filter(Boolean).map((n) => Number(n.replace(/,/g, ''))));
      assert.ok(km.length >= 2, `distances not shown in the list (${km.length})`);
      assert.deepStrictEqual(km, [...km].sort((x, y) => x - y), `not nearest-first: ${km.join(', ')}`);
    });

    it('IT-51 a pin preview shows photo, name, rate and distance and opens the item (FR-53)', async () => {
      // The preview is a tooltip shown on hover or keyboard focus. Pins in the same town overlap,
      // so trigger them through the pin element itself.
      await browser.execute(() => document.querySelector('.leaflet-marker-icon').dispatchEvent(new MouseEvent('mouseover', { bubbles: true })));
      const preview = await $('.listing-map-tooltip .listing-map-preview');
      await preview.waitForDisplayed({ timeout: 10000 });
      const text = await preview.getText();
      assert.ok(/₱/.test(text) && /km/.test(text), `preview lacks rate or distance: ${text}`);
      assert.ok(await preview.$('.listing-map-preview__image, .listing-map-preview__fallback').isExisting(), 'preview has no photo area');
      assert.ok((await preview.$('.listing-map-preview__name').getText()).length > 0, 'preview has no item name');
      // Selecting the pin opens the item's detail page.
      await browser.execute(() => document.querySelector('.leaflet-marker-icon').click());
      await a.waitForUrl(/\/listings\/\d+$/);
    });
  });

  describe('Listing form helpers', () => {
    it('IT-52 owner creates a reusable "Other" category, and the same name is reused (FR-63)', async () => {
      for (const name of ['QA Garden Tools', 'qa garden tools']) {
        await loginAs(fx.users.owner.email, 'owner');
        await browser.url('/owner/listings/create');
        await a.setField('#category_id', '0');
        await browser.pause(800);
        await a.type('#other_category', name);
        await a.type('#name', `QA Hedge Trimmer ${name === 'QA Garden Tools' ? 'A' : 'B'}`);
        await a.type('#description', 'Fixture for the Other category test.');
        await a.type('#location', 'Nasugbu, Batangas');
        await a.setField('#available_from', a.daysFromNow(0));
        await a.setField('#available_until', a.daysFromNow(30));
        await a.type('#price_per_day', '200');
        await browser.pause(1200);
        const map = await $('[x-ref="map"]');
        await map.scrollIntoView({ block: 'center' });
        await browser.pause(800);
        await map.click();
        await a.upload('#listing-photos');
        await (await $('[wire\\:click^="removePendingPhoto"]')).waitForExist({ timeout: 20000 });
        await a.submitWireForm('save');
        // The success message fades quickly; check the new listing in My Listings instead.
        await a.waitForUrl(/\/owner\/listings(\?|$)/);
        await a.waitForText(`QA Hedge Trimmer ${name === 'QA Garden Tools' ? 'A' : 'B'}`);
      }
      await browser.url('/owner/listings/create');
      const matches = await browser.execute(() => Array.from(document.querySelectorAll('#category_id option')).filter((o) => /qa garden tools/i.test(o.textContent)).length);
      assert.strictEqual(matches, 1, `expected one reused "QA Garden Tools" category, found ${matches}`);
    });

    it('IT-53 the price suggestion explains itself and is applied only when the owner clicks it (FR-64)', async () => {
      const firstCategory = await browser.execute(() => Array.from(document.querySelectorAll('#category_id option')).find((o) => o.value && o.value !== '0')?.value);
      await a.setField('#category_id', firstCategory);
      await browser.pause(1500);
      await a.type('#price_per_day', '999');
      // The suggestion needs the item's estimated original price.
      await a.type('#estimated_original_price', '20000');
      await browser.pause(1500);
      await (await $('[wire\\:click="useSuggestedPrice"]')).waitForExist({ timeout: 15000 });
      assert.strictEqual(await (await $('#price_per_day')).getValue(), '999', 'price changed before clicking the suggestion');
      await a.clickWire('useSuggestedPrice');
      await browser.waitUntil(async () => (await (await $('#price_per_day')).getValue()) !== '999', { timeout: 10000, timeoutMsg: 'suggested rate was not applied' });
    });
  });

  describe('Admin fee rules, fulfillment and transactions', () => {
    it('IT-54 admin changes the processing fee; new quotes use it, then it is restored (FR-75)', async () => {
      await asAdmin();
      await browser.url('/admin/payment-settings');
      await a.type('#processing-rate', '15');
      await a.scrollClick('//button[contains(., "Save fee rules")]');
      await browser.pause(1500);
      await loginAs(fx.users.renter2.email, 'renter');
      await browser.url(`/listings/${fx.listings.tent.id}`);
      await a.scrollClick('button[aria-controls="rental-request-modal"]');
      await a.setField('#start_date', a.daysFromNow(50));
      await browser.pause(600);
      await a.setField('#end_date', a.daysFromNow(51));
      // 2 days × ₱300 = ₱600; 15% processing = ₱90 (was ₱60 at 10%).
      await a.waitForText('90.00');
      await asAdmin();
      await browser.url('/admin/payment-settings');
      await a.type('#processing-rate', '10');
      await a.scrollClick('//button[contains(., "Save fee rules")]');
      await browser.pause(1500);
    });

    it('IT-55 owner advances a delivery through Packed → Waiting for courier → Shipped; renter is notified (FR-76)', async () => {
      await loginAs(fx.users.owner.email, 'owner');
      await browser.url(`/owner/rentals/${fx.rentals.delivery}`);
      for (const stage of ['packed', 'waiting_for_courier', 'shipped']) {
        await a.clickWire(`advanceFulfillment('${stage}')`);
        await browser.pause(1500);
        await a.assertNoServerError();
      }
      await loginAs(fx.users.renter.email, 'renter');
      await browser.url(`/renter/rentals/${fx.rentals.delivery}`);
      await a.waitForTextI('shipped');
      assert.ok(!(await $('[wire\\:click="$toggle(\'showCancelForm\')"]').isExisting()), 'ordinary cancellation still offered after shipping');
    });

    it('IT-56 renter 2 joins the waitlist for the grill\'s booked dates (FR-70)', async () => {
      await loginAs(fx.users.renter2.email, 'renter');
      await browser.url(`/listings/${fx.listings.grill.id}`);
      await a.scrollClick('button[aria-controls="rental-request-modal"]');
      await a.setField('#start_date', fx.dates.grillStart);
      await browser.pause(600);
      await a.setField('#end_date', fx.dates.grillEnd);
      await browser.pause(1200);
      await a.clickWire('joinWaitlist');
      await a.waitForText('Joined the waitlist');
    });

    it('IT-57 admin adds a note and cancels the booking with a reason (FR-28)', async () => {
      await asAdmin();
      await browser.url(`/admin/rentals/${fx.rentals.admin}`);
      await a.scrollClick('#rental-tab-actions');
      await a.type('#admin-note', 'QA: renter asked support to cancel.');
      await a.submitWireForm('addNote');
      await browser.pause(1500);
      await a.type('#admin-reason', 'QA: cancelled on the renter\'s request before handover.');
      await a.clickWire('cancelBooking');
      await a.waitForTextI('Cancelled');
    });

    it('IT-58 the History lists each admin action with admin, reason and time; parties are notified (FR-89)', async () => {
      await a.scrollClick('[aria-controls="admin-history-panel"]');
      const panel = await $('#admin-history-panel');
      await panel.waitForDisplayed({ timeout: 10000 });
      await browser.waitUntil(async () => /QA: cancelled on the renter/.test(await panel.getText()) && /QA: renter asked support/.test(await panel.getText()),
        { timeout: 10000, timeoutMsg: 'admin actions missing from History' });
      assert.ok(/Tala Admin/.test(await panel.getText()), 'History does not name the administrator');
      await loginAs(fx.users.renter.email, 'renter');
      await browser.url('/notifications');
      await a.waitForText('An administrator updated the rental');
    });

    it('IT-59 the freed dates trigger one waitlist alert for renter 2 (FR-71)', async () => {
      artisan('rentals:process-reservations');
      artisan('rentals:process-reservations');
      await loginAs(fx.users.renter2.email, 'renter');
      await browser.url('/notifications');
      await a.waitForText('Your waitlisted dates are available');
      const alerts = (await a.pageText()).split('Your waitlisted dates are available').length - 1;
      assert.strictEqual(alerts, 1, `expected one alert, got ${alerts}`);
    });
  });

  describe('Messages, dashboards, records and account closure', () => {
    it('IT-60 message search finds a conversation by item name and filters by type (FR-33)', async () => {
      await loginAs(fx.users.renter2.email, 'renter');
      await browser.url(`/listings/${fx.listings.drone.id}/message`);
      await a.type('form[wire\\:submit="send"] [wire\\:model="body"]', 'QA: is the drone available next month?');
      await a.submitWireForm('send');
      await a.waitForText('QA: is the drone available next month?');
      await loginAs(fx.users.owner.email, 'owner');
      await browser.url('/messages');
      await a.type('input[wire\\:model\\.live\\.debounce\\.250ms="search"]', 'Drone');
      await a.waitForText(fx.users.renter2.name);
      await a.setField('select[wire\\:model\\.live="threadFilter"]', 'listings');
      await browser.pause(1500);
      await a.waitForText(fx.users.renter2.name);
      await a.type('input[wire\\:model\\.live\\.debounce\\.250ms="search"]', 'zzqx-nobody');
      await browser.waitUntil(async () => !(await a.pageText()).includes(fx.users.renter2.name), { timeout: 10000, timeoutMsg: 'search did not filter conversations' });
    });

    it('IT-61 each dashboard shows the progress for its own interface only (FR-85)', async () => {
      await browser.url('/owner/dashboard');
      await a.waitForTextI('Identity verification');
      await a.waitForTextI('Total earnings');
      await a.waitForTextI('Awaiting agreement');
      await loginAs(fx.users.renter.email, 'renter');
      await a.waitForTextI('Pending requests');
      await a.waitForTextI('Active rentals');
      assert.ok(!(await a.pageText()).toLowerCase().includes('total earnings'), 'owner earnings shown on the renter dashboard');
    });

    it('IT-62 admin transaction search narrows the list to the searched item (FR-86)', async () => {
      await asAdmin();
      await browser.url('/admin/rentals');
      await a.type('input[wire\\:model\\.live\\.debounce\\.400ms="search"]', fx.listings.grill.name);
      await browser.waitUntil(async () => {
        const t = await a.pageText();
        return t.includes(fx.listings.grill.name) && !t.includes(fx.listings.lantern.name);
      }, { timeout: 15000, timeoutMsg: 'search did not narrow the transactions' });
    });

    it('IT-63 account closure is blocked while rentals are open (FR-88)', async () => {
      await loginAs(fx.users.renter.email, 'renter');
      await browser.url('/profile');
      await a.scrollClick('button[x-on\\:click*="confirm-user-deletion"]');
      await a.type('#delete_account_password', 'password');
      await a.submitWireForm('deleteUser');
      await a.waitForText('Settle your rental requests, rentals, deposits, damage claims, and disputes before deleting your account.');
    });

    it('IT-64 a member with nothing outstanding can close the account and can no longer log in (FR-88)', async () => {
      await loginAs(fx.users.closer.email, 'renter');
      await browser.url('/profile');
      await a.scrollClick('button[x-on\\:click*="confirm-user-deletion"]');
      await a.type('#delete_account_password', 'password');
      await a.submitWireForm('deleteUser');
      await a.waitForUrl(/^https?:\/\/[^/]+\/?$/);
      await LoginPage.open();
      await LoginPage.loginAs(fx.users.closer.email, 'password');
      await a.waitForText('These credentials do not match our records.');
    });
  });
});
