import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

// Replace only browser/Leaflet boundaries; run the actual Alpine component code.
function mapComponents(navigator = {}, schedule = () => {}) {
    const components = {};
    const pins = [];
    const navigated = [];
    const wireValues = {};
    const layer = () => ({
        handlers: {},
        icon: { handlers: {}, attributes: {}, setAttribute(name, value) { this.attributes[name] = value; }, addEventListener(name, handler) { this.handlers[name] = handler; } },
        addTo() { return this; },
        getElement() { return this.icon; },
        on(event, handler) { this.handlers[event] = handler; return this; },
        bindPopup(content) { this.popup = content; return this; },
        bindTooltip(content) { this.tooltip = content; return this; },
        openTooltip() { this.tooltipOpen = true; },
        closeTooltip() { this.tooltipOpen = false; },
        setLatLng(position) { this.position = position; return this; },
        getLatLng() { return this.position; },
        clearLayers() {},
    });
    const L = {
        Icon: { Default: { prototype: {}, mergeOptions() {} } },
        map() {
            return Object.assign(layer(), {
                setView(center, zoom, options) { this.center = Array.from(center); this.zoom = zoom; this.viewOptions = options; return this; },
                stop() { return this; },
                invalidateSize() {},
            });
        },
        tileLayer: layer,
        layerGroup: layer,
        marker(position, options) {
            const marker = Object.assign(layer(), { position, options });
            pins.push(marker);
            return marker;
        },
        circleMarker(position) { return Object.assign(layer(), { position }); },
    };
    const document = {
        addEventListener(event, callback) { callback(); },
        createElement(tagName) {
            return {
                tagName, style: {}, children: [], textContent: '',
                append(...children) { this.children.push(...children); },
                addEventListener() {},
                set innerHTML(value) { throw new Error('Listing preview must not parse HTML'); },
            };
        },
    };
    const source = readFileSync(new URL('../resources/js/map.js', import.meta.url), 'utf8').replace(/^import .*;\r?\n/gm, '');
    const pinsSource = readFileSync(new URL('../resources/js/listing-map-pin.js', import.meta.url), 'utf8').replace(/^export /gm, '');
    vm.runInNewContext(`${pinsSource}\n${source}`, {
        L, document, markerIcon2x: '', markerIcon: '', markerShadow: '',
        Alpine: { data(name, factory) { components[name] = factory; } },
        setTimeout: schedule, navigator,
        window: { location: { assign(url) { navigated.push(url); } } },
    });
    function component(name, ...args) {
        return Object.assign(components[name](...args), {
            $refs: { map: { scrollIntoView() {} } }, $watch() {},
            $wire: { set(name, value) { wireValues[name] = value; }, get(name) { return wireValues[name]; } },
        });
    }
    return { component, pins, navigated, wireValues };
}

test('owner pin fills address while retaining precise coordinates', async () => {
    const { component, wireValues } = mapComponents();
    const picker = component('locationPicker', null, null);
    picker.$wire.resolvePinnedAddress = async () => ({ address: 'Manila, Philippines' });
    await picker.reportPosition({ lat: 14.599512345, lng: 120.984212345 });
    assert.equal(wireValues.latitude, 14.5995123);
    assert.equal(wireValues.longitude, 120.9842123);
    assert.equal(wireValues.location, 'Manila, Philippines');
    assert.equal(picker.addressLoading, false);
});

test('a busy geocoder automatically retries the latest pin and fills the address', async () => {
    const waits = [];
    const { component, wireValues } = mapComponents({}, (done, delay) => { waits.push(delay); done(); });
    const picker = component('locationPicker', null, null);
    let calls = 0;
    picker.$wire.resolvePinnedAddress = async () => ++calls === 1
        ? { address: null, retry_after: 1 } : { address: 'Latest pin, Manila' };
    await picker.reportPosition({ lat: 14.6, lng: 121 });
    assert.equal(calls, 2);
    assert.deepEqual(waits, [1000]);
    assert.equal(wireValues.location, 'Latest pin, Manila');
    assert.equal(picker.addressLoading, false);
});

test('moving the pin cancels an older queued retry instead of replacing the latest address', async () => {
    const timers = [];
    const { component, wireValues } = mapComponents({}, done => timers.push(done));
    const picker = component('locationPicker', null, null);
    let calls = 0;
    picker.$wire.resolvePinnedAddress = async () => ++calls === 1
        ? { address: null, retry_after: 1 } : { address: 'New pin address' };
    const old = picker.reportPosition({ lat: 1, lng: 1 });
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(timers.length, 1);
    await picker.reportPosition({ lat: 2, lng: 2 });
    timers.shift()();
    await old;
    assert.equal(calls, 2);
    assert.equal(wireValues.location, 'New pin address');
    assert.equal(wireValues.latitude, 2);
});

test('automatic address retries are bounded and retain an editable manual address', async () => {
    const { component, wireValues } = mapComponents({}, done => done());
    const picker = component('locationPicker', null, null);
    wireValues.location = 'Pickup gate details';
    let calls = 0;
    picker.$wire.resolvePinnedAddress = async () => { calls++; return { address: null, retry_after: 1 }; };
    await picker.reportPosition({ lat: 14, lng: 120 });
    assert.equal(calls, 3);
    assert.equal(wireValues.location, 'Pickup gate details');
    assert.equal(picker.addressLoading, false);
    assert.match(picker.locationStatus, /manually or retry/);
});

test('owner current location places the pin and fills its resolved address', async () => {
    const { component, wireValues } = mapComponents({ geolocation: {
        getCurrentPosition(done) { done({ coords: { latitude: 14.6, longitude: 121 } }); },
    } });
    const picker = component('locationPicker', null, null);
    picker.init();
    picker.$wire.resolvePinnedAddress = async () => ({ address: 'Current pin address' });
    picker.useMyLocation();
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(wireValues.location, 'Current pin address');
    assert.equal(wireValues.latitude, 14.6);
    assert.equal(wireValues.longitude, 121);
    assert.equal(picker.locating, false);
});

test('first current-location click centers the map after permission returns without waiting for the address', () => {
    let locate;
    let locationOptions;
    let requests = 0;
    const { component, wireValues, pins } = mapComponents({ geolocation: {
        getCurrentPosition(done, fail, options) { requests++; locate = done; locationOptions = options; },
    } });
    const picker = component('locationPicker', 14, 120);
    picker.init();
    const actions = [];
    picker.map.stop = () => actions.push('stop');
    picker.map.invalidateSize = options => { assert.equal(options.pan, false); actions.push('resize'); };
    picker.$refs.map.scrollIntoView = options => { assert.equal(options.block, 'center'); actions.push('show-map'); };
    picker.$wire.resolvePinnedAddress = () => new Promise(() => {});
    picker.useMyLocation();
    picker.useMyLocation();
    assert.equal(requests, 1);
    assert.equal(picker.locating, true);
    locate({ coords: { latitude: 13.75, longitude: 121.06 } });
    assert.deepEqual(picker.map.center, [13.75, 121.06]);
    assert.equal(picker.map.zoom, 14);
    assert.equal(picker.map.viewOptions.animate, false);
    assert.equal(pins.length, 1, 'The existing pin is moved, not duplicated');
    assert.equal(pins[0].position.lat, 13.75);
    assert.equal(pins[0].position.lng, 121.06);
    assert.deepEqual(actions, ['stop', 'resize', 'show-map']);
    assert.equal(wireValues.latitude, 13.75);
    assert.equal(wireValues.longitude, 121.06);
    assert.equal(picker.locating, false);
    assert.equal(picker.addressLoading, true, 'Address lookup must not delay moving the map');
    assert.equal(locationOptions.maximumAge, 0);
    assert.equal(locationOptions.enableHighAccuracy, true);
});

test('late lookup cannot overwrite the latest pin address', async () => {
    const { component, wireValues } = mapComponents();
    const picker = component('locationPicker', null, null);
    const resolve = [];
    picker.$wire.resolvePinnedAddress = () => new Promise(done => resolve.push(done));
    const old = picker.reportPosition({ lat: 1, lng: 1 });
    const latest = picker.reportPosition({ lat: 2, lng: 2 });
    resolve[1]({ address: 'Latest address' });
    await latest;
    resolve[0]({ address: 'Stale address' });
    await old;
    assert.equal(wireValues.location, 'Latest address');
    assert.equal(wireValues.latitude, 2);
    assert.equal(picker.addressLoading, false);
});

test('manual edits made during lookup survive its response', async () => {
    const { component, wireValues } = mapComponents();
    const picker = component('locationPicker', null, null);
    let resolve;
    picker.$wire.resolvePinnedAddress = () => new Promise(done => { resolve = done; });
    const pending = picker.reportPosition({ lat: 0, lng: 0 });
    wireValues.location = 'My pickup gate';
    resolve({ address: 'Provider address' });
    await pending;
    assert.equal(wireValues.location, 'My pickup gate');
    assert.match(picker.locationStatus, /edited address was kept/);
});

test('failed lookup retains manual address and pin and permits retry', async () => {
    const { component, wireValues } = mapComponents();
    const picker = component('locationPicker', null, null);
    wireValues.location = 'Manual pickup';
    picker.$wire.resolvePinnedAddress = async () => { throw new Error('Unavailable'); };
    await picker.reportPosition({ lat: 0, lng: 0 });
    assert.equal(wireValues.location, 'Manual pickup');
    assert.equal(wireValues.latitude, 0);
    assert.equal(picker.addressLoading, false);
    picker.$wire.resolvePinnedAddress = async () => ({ address: 'Resolved address' });
    await picker.reportPosition({ lat: 0, lng: 0 });
    assert.equal(wireValues.location, 'Resolved address');
});

test('owner location denial offers map fallback and stale location cannot move a newer manual pin', async () => {
    let success, failure;
    const { component, wireValues } = mapComponents({ geolocation: { getCurrentPosition(ok, error) { success = ok; failure = error; } } });
    const picker = component('locationPicker', null, null);
    picker.init();
    picker.useMyLocation();
    failure({ code: 1 });
    assert.equal(picker.locating, false);
    assert.match(picker.locationError, /denied/);
    picker.useMyLocation();
    await picker.reportPosition({ lat: 2, lng: 2 });
    success({ coords: { latitude: 1, longitude: 1 } });
    assert.equal(wireValues.latitude, 2);
    assert.equal(picker.locating, false);
});

test('late device location failure cannot add an error after a newer manual pin', async () => {
    let fail;
    const { component } = mapComponents({ geolocation: { getCurrentPosition(ok, error) { fail = error; } } });
    const picker = component('locationPicker', null, null);
    picker.init();
    picker.useMyLocation();
    await picker.reportPosition({ lat: 14, lng: 120 });
    fail({ code: 1 });
    assert.equal(picker.locationError, '');
    assert.equal(picker.locationStatus, 'Pin updated.');
    assert.equal(picker.locating, false);
});

for (const [lat, lng] of [[0, 120], [14, 0], [0, 0]]) {
    test(`owner picker initializes at valid coordinates ${lat}, ${lng}`, () => {
        const { component, pins } = mapComponents();
        const picker = component('locationPicker', lat, lng);
        picker.init();
        assert.deepEqual(picker.map.center, [lat, lng]);
        assert.equal(picker.map.zoom, 14);
        assert.equal(pins.length, 1);
        assert.deepEqual(Array.from(pins[0].position), [lat, lng]);
    });

    test(`listing map initializes and marks valid coordinates ${lat}, ${lng}`, () => {
        const { component } = mapComponents();
        const map = component('listingsMap', [], lat, lng);
        map.init();
        assert.deepEqual(map.map.center, [lat, lng]);
        assert.equal(map.map.zoom, 14);
        assert.deepEqual(Array.from(map.locationMarker.position), [lat, lng]);
    });
}

test('missing coordinates use the default center and do not add a location pin', () => {
    for (const [lat, lng] of [[null, null], [0, null], [null, 0]]) {
        const { component, pins } = mapComponents();
        const picker = component('locationPicker', lat, lng);
        picker.init();
        assert.deepEqual(picker.map.center, [14.5995, 120.9842]);
        assert.equal(picker.map.zoom, 11);
        assert.equal(pins.length, 0);
        const map = component('listingsMap', [], lat, lng);
        map.init();
        assert.deepEqual(map.map.center, [14.5995, 120.9842]);
        assert.equal(map.locationMarker, null);
    }
});

test('picker map click and marker drag report zero coordinates without losing precision', () => {
    const { component, wireValues } = mapComponents();
    const picker = component('locationPicker', null, null);
    picker.init();
    picker.map.handlers.click({ latlng: { lat: 0, lng: 120.123456789 } });
    assert.deepEqual(wireValues, { latitude: 0, longitude: 120.1234568 });
    picker.marker.position = { lat: 14.123456789, lng: 0 };
    picker.marker.handlers.dragend();
    assert.deepEqual(wireValues, { latitude: 14.1234568, longitude: 0 });
});

test('pin preview contains a photo and literal name and daily price without parsing HTML', () => {
    const { component, pins } = mapComponents();
    const name = '<img src=x onerror=alert(1)>';
    const map = component('listingsMap', [{
        lat: 0, lng: 0, name, image: '/storage/listings/camera.jpg', price: '1,234.50', distance: '0.0 km away', url: '/listings/7',
    }], null, null);
    map.init();
    const preview = pins[0].tooltip;
    assert.equal(typeof preview, 'object');
    const image = preview.children.find((child) => child.tagName === 'img');
    assert.equal(image.src, '/storage/listings/camera.jpg');
    assert.equal(image.alt, name);
    assert.equal(preview.children.find((child) => child.tagName === 'strong').textContent, name);
    assert.equal(preview.children.find((child) => child.className === 'listing-map-preview__price').textContent, '₱1,234.50 per day');
    assert.equal(preview.children.find((child) => child.className === 'listing-map-preview__distance').textContent, '0.0 km away');
    assert.equal(pins[0].options.title, `${name} · ₱1,234.50 per day`);
    assert.equal(pins[0].icon.attributes.role, 'link');
    assert.equal(preview.children.find((child) => child.tagName === 'a').href, '/listings/7');
});

test('selecting either pin navigates directly to that listing detail', () => {
    const { component, pins, navigated } = mapComponents();
    const map = component('listingsMap', [
        { lat: 0, lng: 0, name: 'Camera', image: null, price: '100.00', distance: null, url: '/listings/7' },
        { lat: 1, lng: 1, name: 'Drill', image: null, price: '200.00', distance: null, url: '/listings/9' },
    ], null, null);
    map.init();
    assert.equal(typeof pins[1].handlers.click, 'function');
    pins[1].handlers.click();
    pins[0].handlers.click();
    assert.deepEqual(navigated, ['/listings/9', '/listings/7']);
    assert.equal(pins[0].tooltip.children.some((child) => child.tagName === 'img'), false);
});

test('hourly map previews and accessible pin labels show the hourly rate', () => {
    const { component, pins } = mapComponents();
    component('listingsMap', [{ lat: 0, lng: 0, name: 'Camera', price: '50.00', priceUnit: 'hour', url: '/listings/7' }], null, null).init();
    assert.equal(pins[0].tooltip.children.find((child) => child.className === 'listing-map-preview__price').textContent, '₱50.00 per hour');
    assert.equal(pins[0].options.title, 'Camera · ₱50.00 per hour');
    assert.match(pins[0].icon.attributes['aria-label'], /per hour/);
});

test('Enter and Space select the focused listing pin while other keys keep map navigation', () => {
    const { component, pins, navigated } = mapComponents();
    const map = component('listingsMap', [
        { lat: 0, lng: 0, name: 'Camera', image: null, price: '100.00', distance: null, url: '/listings/7' },
    ], null, null);
    map.init();
    assert.equal(typeof pins[0].icon.handlers.keydown, 'function');
    const prevented = [];
    for (const key of ['ArrowRight', 'Enter', ' ']) {
        pins[0].icon.handlers.keydown({ key, preventDefault() { prevented.push(key); }, stopPropagation() {} });
    }
    assert.deepEqual(navigated, ['/listings/7', '/listings/7']);
    assert.deepEqual(prevented, ['Enter', ' ']);
});
