// Written content of the QA report. Test-case rows, results, timestamps and screenshots come from
// the last run; findings, environment notes and recommendations are maintained here by hand.
module.exports = {
  title: 'Lendly QA Test Report — Selenium (Requirements v2)',
  tester: 'Andre Cachola (QA / Tester)',

  levels: {
    UT: 'Individual components and their validation: forms, error messages, filters, calculators and widgets.',
    IT: 'Each module and the processes added in Requirements v2, working with the components it uses, including unhappy paths.',
    ST: 'One complete rental from registration to reviews, following the Requirements v2 process flow.',
    SM: 'Quick checks that the key pages open and respond.',
  },

  // Filled from the PHPUnit run on the same code.
  phpunit: {
    what: "The developers' backend tests, run on an in-memory database.",
    total: '1,078',
    passed: '1,077',
    failed: '1 (D-09)',
    summary: '1,077 of 1,078 passed after `composer install` (which fixed the 46 Google sign-in tests that were missing the laravel/socialite package). The one failure is time-dependent and exposes defect D-09: run between 10:00 and 10:59 PM, the request form\'s default return time comes before its start time.',
  },
  openSevere: 'None found',

  environment: [
    ['Branch', '`qa/e2e-requirements-v2` (from `main @ 1e952af`)'],
    ['Requirements baseline', 'Lendly Revised Requirements v2: FR-01 to FR-89, NFR-01 to NFR-26'],
    ['Tool', 'Selenium WebDriver protocol via WebdriverIO 10 + ChromeDriver 156, Mocha'],
    ['Browser / OS', 'Google Chrome 156 (visible, maximized) on Windows 11'],
    ['App', 'Laravel app (XAMPP PHP 8.2): automated runs on a separate test server at `http://127.0.0.1:8001`; manual tests on `http://localhost:8000` with the MySQL database (21 pending migrations applied after a backup)'],
    ['Test data', 'Fresh throwaway SQLite database per run, seeded with test accounts, categories and prepared scenarios. The real MySQL database is never touched.'],
    ['Evidence', 'A full-screen screenshot after every test, including the Windows taskbar clock'],
  ],

  findingsLead: 'No Severity 1 or 2 defects were found. One medium and eight low-severity findings need a developer decision.',
  findings: [
    ['D-06', 'Receipt shows the payment time in UTC, 8 hours behind Philippine time', 'Confirm a payment, open the receipt (ST-12 screenshot: receipt "Paid Nov 03, 2026 2:25 AM" while the taskbar clock shows 10:25 AM).', 'Receipt time in Philippine time, like the payment deadline and deposit release times', 'The app timezone is UTC and the owner, renter and admin receipts print paid_at without converting to Asia/Manila (FR-17)', 'Medium'],
    ['D-01', 'Email address is silently rewritten at sign-up', 'Register as qa.owner.x@gmail.com, then look the account up as admin.', 'Address kept as typed, or the user is told it is shortened', 'Stored as qaownerx@gmail.com: dots and +tags are removed with no notice (FR-01)', 'Low'],
    ['D-02', 'Map pin is not checked against the typed location', 'Type "Nasugbu, Batangas", click the map without panning, publish (ST-05).', 'Warning when the pin is far from the typed location', 'Publishes with the pin at the default map centre (Manila), about 70 km away (FR-47)', 'Low'],
    ['D-03', 'Duplicate map-pin error on the listing form', 'Submit the listing form without a pin (UT-11 screenshot).', 'One "Place a pin on the map" message', 'Shown twice in the error summary (latitude and longitude)', 'Low'],
    ['D-04', 'Live listings cannot be moderated with a reason', 'Admin › Listings › a published listing (IT-25).', 'Admin can record a reason the owner sees (FR-27)', 'Published listings only offer "Remove": the owner is notified, but no reason is recorded. "Reject with reason" exists only for pending listings, which owners never create.', 'Low'],
    ['D-09', 'Rental request form has invalid default times between 10:00 and 10:59 PM', 'Open "Request to rent" at, e.g., 10:50 PM and submit without changing the times. Backend test NotificationsAndChatTest::test_invalid_or_unauthorized_decisions_do_not_send_status_notifications fails at that hour.', 'Default start and return times form a valid period at any time of day', 'Default start = 23:00 (next whole hour) and default return = start + 1 hour = 00:00 on the same date, so the return is before the start; submitting shows "Return time must follow the start time" (Create.php mount). The renter must fix the times by hand.', 'Low'],
    ['D-07', 'Intermittent Google "Access blocked" error', 'MT-01: log out, click Continue with Google again (screenshot in MT-01).', 'Google sign-in page every time', 'Once, Google showed "Access blocked: Authorization Error, Error 400: invalid_request"; the next attempt a minute later succeeded. Not reproduced since. Check the OAuth request parameters and the Google Cloud consent-screen settings.', 'Low'],
    ['D-08', 'Face-comparison setup docs list an unsupported Python version', 'Follow docs/identity-verification.md with Python 3.14.', 'Setup works on the documented Python versions', 'Docs say "Python 3.10+", but the pinned numpy 2.2.6 / OpenCV 4.12 do not install on Python 3.14; Python 3.13 works', 'Low'],
    ['D-05', 'Selfie field not enforced in the browser', 'Open ID review, tick consent, leave both photos empty, submit (UT-07).', 'Both required photos blocked in the browser', 'Only the ID-front field blocks the submit; the selfie (marked required) is checked by the server only', 'Low'],
  ],
  envNotes: [
    'Google sign-in (FR-55) and the face comparison step (FR-59) were tested manually (see Manual test cases). The automated suite creates the pending ID submission directly (`fixtures/identity-pending.php`) and tests the admin review screen for real (FR-60).',
    'For the manual tests, the Google OAuth credentials were added to `.env`, `composer install` was run, and the local face-comparison runtime was installed (Python 3.13 virtual environment with the pinned numpy 2.2.6 / OpenCV 4.12 and the official OpenCV Zoo models, checksums verified).',
    'Time-based rules (FR-19, FR-20, FR-74, FR-79) were tested by moving a rental\'s dates in the throwaway test database (`fixtures/tweak.php`) and running the scheduled jobs by hand (overdue check, return reminders, reservation processing), instead of waiting real days.',
    'Map location features (FR-49 to FR-51, FR-53) were tested with Chrome\'s location override set to Nasugbu, Batangas, with location permission granted, and one listing placed in Manila (about 70 km away) to check distance filtering and sorting.',
    'The PHP GD extension is disabled in XAMPP; the backend suite was run with GD enabled for that run only.',
  ],

  recommendations: [
    ['Fix', [
      'D-06 (first): show receipt times in Philippine time (convert paid_at to Asia/Manila on the owner, renter and admin receipts, or set the app timezone).',
      'D-09: when the next whole hour is 11 PM, roll the default return time over to the next day (or start the default on the next day).',
      'D-01: tell users at sign-up that Gmail dots and +tags are ignored, or keep the typed address for display.',
      'D-02: warn when the pin is far from the typed location, or fill the location text from the pin.',
      'D-03: show the map-pin error once.',
      'D-04: let admins record a reason when removing a published listing, shown to the owner (FR-27).',
      'D-07: check the Google OAuth request parameters and consent-screen settings; log Google callback errors so the cause can be traced.',
      'D-08: change the docs to "Python 3.10 to 3.13", or update the pinned numpy/OpenCV versions for Python 3.14.',
      'D-05: mark the selfie input as required in the browser, or drop the required marker when the camera is used.',
    ]],
    ['Test next', [
      'Re-run the time-based tests once with the real scheduler running (`php artisan schedule:work`) to confirm the jobs fire on their own.',
      'Hourly rentals (FR-65, FR-79 hourly late fees) and the phone-width layout (NFR-15).',
      'Backend API tests in Postman, as listed in the Test Plan.',
    ]],
  ],

  // Modules and requirement IDs from Requirements v2, Part V.A.
  modules: [
    ['User Registration & Login', ['FR-01', 'FR-02', 'FR-03', 'FR-04', 'FR-39', 'FR-54', 'FR-55', 'FR-56', 'FR-57']],
    ['Item Listing & Management', ['FR-05', 'FR-06', 'FR-07', 'FR-08', 'FR-47', 'FR-63', 'FR-64', 'FR-65', 'FR-66', 'FR-67']],
    ['Item Search & Browsing', ['FR-09']],
    ['Map & Nearby Item Search', ['FR-48', 'FR-49', 'FR-50', 'FR-51', 'FR-52', 'FR-53']],
    ['Item Details & Availability', ['FR-10']],
    ['Rental Request & Approval', ['FR-11', 'FR-12', 'FR-13']],
    ['Rental Booking', ['FR-14', 'FR-15', 'FR-40']],
    ['Payment & Transactions', ['FR-16', 'FR-17', 'FR-41', 'FR-42', 'FR-72', 'FR-73', 'FR-74', 'FR-75']],
    ['Item Pickup or Delivery', ['FR-18', 'FR-43', 'FR-44', 'FR-76']],
    ['Rental Tracking', ['FR-19', 'FR-20', 'FR-79']],
    ['Item Return & Condition', ['FR-21', 'FR-22', 'FR-77', 'FR-78']],
    ['Ratings & Reviews', ['FR-23', 'FR-45', 'FR-83']],
    ['Rental History', ['FR-24']],
    ['Owner Earnings & Records', ['FR-25']],
    ['Admin Management', ['FR-26', 'FR-27', 'FR-28', 'FR-89']],
    ['Reports & Disputes', ['FR-29', 'FR-30', 'FR-46', 'FR-80']],
    ['Messaging', ['FR-31', 'FR-32', 'FR-33']],
    ['Rental Cancellation', ['FR-34', 'FR-35', 'FR-36', 'FR-37', 'FR-84']],
    ['User Profile Management', ['FR-38', 'FR-88']],
    ['Identity Review', ['FR-58', 'FR-59', 'FR-60', 'FR-61', 'FR-62']],
    ['Rental Cart', ['FR-68', 'FR-69']],
    ['Availability Waitlist', ['FR-70', 'FR-71']],
    ['Security Deposit', ['FR-81', 'FR-82']],
    ['Dashboards & Record Controls', ['FR-85', 'FR-86']],
    ['Notifications', ['FR-87']],
  ],
  notCovered: {
    'FR-55': 'Manual test MT-01 (real Google sign-in cannot be automated)',
    'FR-59': 'Manual test MT-02 (needs real face photos): MT-02b passed; MT-02a not completed yet',
  },
  // Status shown in the traceability matrix for requirements verified by manual tests.
  manualStatus: {
    'FR-55': { text: 'Pass (manual)', color: '1E7B34', fill: 'E8F5E9' },
    'FR-59': { text: 'Partial (manual)', color: '9C6500', fill: 'FFF8E1' },
  },

  manualLead: 'Two requirements cannot be automated: real Google sign-in (Google blocks automated browsers and needs a real account) and face comparison (needs real face photos). The tester ran them by hand on November 3, 2026, following the Manual Test Guide, with full-screen screenshots (taskbar clock visible).',
  manualTests: [
    {
      id: 'MT-01', req: 'FR-55', title: 'Google sign-in creates or links one member account and requires choosing Owner or Renter',
      steps: '1. Log in page › Continue with Google, sign in with Gmail. 2. Choose Renter. 3. Log out, sign in with Google again. 4. Check the database for duplicate accounts.',
      expected: 'One member account linked to Google (no duplicate on repeat sign-in); Owner/Renter choice required before entering; new account directed to ID review.',
      actual: 'Account created and linked (1 account for the Gmail after two sign-ins); choice page shown both times; new account landed in the Renter interface on ID review (FR-61). One attempt showed Google\'s "Access blocked: Error 400 invalid_request"; the next attempt succeeded (see D-07).',
      result: 'PASS', at: 'Nov 3, 2026 10:21–10:24 PM',
      screenshots: [
        ['manual/MT-01-step1-PASS.png', 'Step 1: Owner/Renter choice after Google sign-in'],
        ['manual/MT-01-step2-PASS.png', 'Step 2: Renter interface, new account directed to ID review'],
        ['manual/MT-01-observation-google-error.png', 'Observation: one Google "Access blocked" error (D-07)'],
        ['manual/MT-01-step3-PASS.png', 'Step 3: second Google sign-in reuses the same account'],
      ],
    },
    {
      id: 'MT-02a', req: 'FR-59', title: 'Clear National ID and selfie of the same person are accepted for admin review',
      steps: '1. ID review › consent. 2. Upload a clear Philippine National ID front and a selfie. 3. Submit. 4. Admin › Identity submissions.',
      expected: 'Submission accepted with a similarity score for the admin; not approved automatically.',
      actual: 'Not completed: no valid National ID submission was made yet (the attempt used a driver\'s license, see MT-02b).',
      result: 'NOT RUN', at: '—', screenshots: [],
    },
    {
      id: 'MT-02b', req: 'FR-59', title: 'An ID photo without a single clear portrait is blocked at upload',
      steps: '1. ID review › consent. 2. Upload an ID front where the portrait is small or not clearly detectable, plus a selfie. 3. Submit.',
      expected: 'Upload blocked with a clear message; nothing reaches the admin.',
      actual: 'Blocked: "Use a clear ID front with a large, unobstructed portrait." No submission was created.',
      result: 'PASS', at: 'Nov 3, 2026 10:40 PM',
      screenshots: [['manual/MT-02b-PASS.png', 'ID front rejected by the local face check']],
    },
  ],

  scriptsLead: 'The automated test scripts used for this report, as committed on the test branch. Specs hold the test cases; support files hold shared helpers; fixtures prepare the test data.',
  scripts: [
    ['tests/e2e/wdio.conf.cjs', 'Test runner configuration: browser, run order, and the full-screen screenshot and result log after every test.'],
    ['tests/e2e/run.sh', 'One-command runner: builds the throwaway test database, starts the app, runs the tests, stops the app.'],
    ['tests/e2e/specs/component.e2e.cjs', 'Unit tests (UT).'],
    ['tests/e2e/specs/module.e2e.cjs', 'Integration tests IT-01 to IT-28.'],
    ['tests/e2e/specs/features.e2e.cjs', 'Integration tests IT-29 to IT-42 (Requirements v2 processes).'],
    ['tests/e2e/specs/extra.e2e.cjs', 'Integration tests IT-43 to IT-64 (time-based rules, map location and the remaining v2 processes).'],
    ['tests/e2e/specs/full-cycle.e2e.cjs', 'System test (ST): the complete rental flow.'],
    ['tests/e2e/specs/smoke.welcome.e2e.cjs', 'Smoke test: welcome page.'],
    ['tests/e2e/specs/smoke.browse.e2e.cjs', 'Smoke tests: marketplace.'],
    ['tests/e2e/specs/smoke.login.e2e.cjs', 'Smoke tests: login.'],
    ['tests/e2e/support/actions.cjs', 'Shared browser actions (typing, clicking Livewire controls, dialogs, waits, uploads).'],
    ['tests/e2e/support/session.cjs', 'Login helpers and fixture loader.'],
    ['tests/e2e/support/livewire.cjs', 'Livewire wait helpers.'],
    ['tests/e2e/support/capture-screen.ps1', 'Full-screen capture of the primary monitor, including the taskbar clock.'],
    ['tests/e2e/pages/LoginPage.cjs', 'Page object: login form and interface picker.'],
    ['tests/e2e/pages/BrowsePage.cjs', 'Page object: marketplace search.'],
    ['tests/e2e/pages/WelcomePage.cjs', 'Page object: welcome page.'],
    ['tests/e2e/fixtures/seed.php', 'Test data: accounts, listings and prepared rental scenarios, created through the app\'s own services.'],
    ['tests/e2e/fixtures/identity-pending.php', 'Test data: a pending identity submission for the admin review tests.'],
    ['tests/e2e/fixtures/tweak.php', 'Test-only date mover: shifts a rental\'s dates in the test database for the time-based tests.'],
  ],

  howToRun: {
    lead: 'Everything runs from Git Bash in the project folder (Node, Chrome and XAMPP PHP are needed; MySQL is not). Chrome opens visibly; avoid using the computer during the run, because every screenshot captures the whole screen.',
    steps: [
      'One-time setup: `npm install`.',
      'Run all tests: `npm run test:e2e`. In VS Code: Ctrl+Shift+P › Run Test Task.',
      'Run one level: `npm run test:e2e:component`, `npm run test:e2e:module` or `npm run test:e2e:integrated`.',
      'If port 8000 is busy with your own `php artisan serve`, add `E2E_PORT=8001` in front of the command.',
      'Rebuild this report from the last run: `npm run test:e2e:report`.',
    ],
    commands: [
      'npm run test:e2e',
      'E2E_HEADLESS=1 npm run test:e2e      # hidden browser, page-only screenshots',
      'npm run test:e2e:report',
    ],
    after: 'Each run prints ✓/✖ per test in the terminal and saves one full-screen screenshot per test in `tests/e2e/artifacts/screens/` (named by test ID and result).',
  },
};
