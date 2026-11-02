# Owner listing map — 2 November 2026

Request: make Use current location visible and fill the Location text from a map pin on `/owner/listings/create`.

## Before

The local build had a small `Use my location` text link. A single real pin lookup did fill an address during inspection, so the blank-field behavior was not consistently reproduced in this browser. The provider's one-request-per-second slot, however, returned the same empty result for a busy slot as for a failed lookup; a latest pin could therefore finish without an address when the provider was busy.

[Actual before screenshot](location-map-fix-evidence/before.jpg)

## After

- Prominent `Use current location` button with icon, border, keyboard focus, and locating state.
- Busy lookups return explicit retry information. The latest pin retries automatically, up to three total attempts, rather than treating a busy slot as a failed address lookup.
- Older pin responses/retries cannot overwrite a newer pin. A manual edit made during lookup is retained. Provider errors still allow manual entry and the existing retry control.
- Rebuilt production assets. Actual browser click on the map populated the field with `S&R Membership Shopping, Bradco Avenue, Parañaque District 1, Parañaque, Metro Manila, 1702, Philippines`; no browser JavaScript errors appeared. No listing was published.

[Actual after screenshot](location-map-fix-evidence/after.jpg)

## Verification

- 13 PHP listing/address tests passed, 74 assertions.
- All 54 JavaScript tests passed, including current-location pin/address updates, busy retry, retry limits, stale pin cancellation, and existing manual-edit/permission-denial cases.
- Production build, PHP formatting, and whitespace checks passed.
- Browser verified the visible button and real pin-to-address update. Device geolocation was simulated in automated tests; the user's actual device location was not requested.

Raw results: `storage/logs/location-map-php.xml`, `location-map-php.txt`, `location-map-js-final.txt`, and `location-map-build.txt`.

## First-click current location follow-up

The current-location callback now stops any pending Leaflet movement, refreshes map dimensions, and centers the map immediately without a pan animation. It moves the existing pin (or creates one), brings the map into view, and starts address lookup independently. It requests a fresh, high-accuracy position instead of reusing a position cached up to one minute ago. Manual pin changes made while waiting for geolocation still take precedence.

Regression verified: one click with delayed permission/location response moves and zooms the map, reuses the pin, and updates coordinates before the address request completes; repeated clicks while locating do not launch another request. All **55 JavaScript tests passed**, and the production build passed. Device geolocation remains simulated in these tests; actual location permissions remain the user's browser choice.

Follow-up logs: `storage/logs/current-location-first-click-js.txt` and `current-location-first-click-build.txt`.
