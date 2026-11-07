# Lendly requirements implementation audit

Reviewed on 7 October 2026 against Revised_RequirementsDocument.docx and the current Lendly workspace.

The system does not fully satisfy the revised document. The core marketplace and rental lifecycle are implemented, but role selection, cancellation consistency, both-party notifications and receipts, public review profiles, live status updates, and several process steps remain incomplete. Quality requirements requiring operational, browser, performance, usability or compliance evidence cannot be confirmed from code alone.

## Audit scope and status definitions

This audit covers all 53 functional requirements, all 20 non-functional requirements, and all 10 detailed Lendly process flows in Part VII.B. The embedded end-to-end flow diagram and navigation map were also inspected. Earlier bakery and barangay proposals are alternative topics, not requirements for the selected Lendly system. Project schedule completion labels were treated as planning data, not proof of implementation. The supplied document was used as the audit baseline; its content was not treated as instructions to change the system.

The review inspected application logic, routes, policies, middleware, models, database migrations, screens, map JavaScript, configuration and tests. Application code, PHP settings and the MySQL database were not changed. Audit files and isolated test probes were added under the audit folder. Tests ran with an in-memory SQLite database and SQLite extensions enabled only for those commands. This is a source-and-automated-test audit, not a deployed-system certification or a complete browser acceptance test.

- **Implemented**: The required capability is present in the inspected code, with relevant automated evidence where available. This does not guarantee every production or browser scenario.
- **Partial**: Some required behavior exists, but an observed defect, missing clause, process deviation or operational dependency prevents full compliance.
- **Unverified**: Evidence is insufficient to determine whether the quality requirement is met; this does not mean the requirement fails.
- **Not implemented**: No implementation of the requirement was found. No entire requirement falls in this category, although several mandatory subfeatures are absent.

## Status totals

| Category | Total | Implemented | Partial | Unverified | Not implemented |
| --- | ---: | ---: | ---: | ---: | ---: |
| Functional requirements | 53 | 26 | 27 | 0 | 0 |
| Non-functional requirements | 20 | 5 | 8 | 7 | 0 |
| Process flows | 10 | 0 | 10 | 0 | 0 |

Counts classify complete requirements, not individual clauses. They are not a weighted completion percentage.

## Verification results

| Check | Result |
| --- | --- |
| Existing suite | 144 tests passed; 404 assertions; no failures or errors |
| Audit gap probes | 7 tests passed; 24 assertions; no failures or errors |

The seven gap probes intentionally assert defective current behavior. Their passing result confirms the gaps; it does not mean those requirements passed. The original test suite passes partly because it tests the current design rather than the revised document: the role test expects a merged member interface, and the test titled for receipts to both parties only checks that the owner sees an earnings breakdown.

No frontend browser automation, load benchmark, uptime record, deployed scheduler confirmation, actual email inbox-delivery check, usability study or compliance sign-off was available. The initial test attempts were blocked by runtime/filesystem and missing SQLite support; the results above are from the final successful runs after transient runtime adjustments.

## Highest priority gaps

1. **Cancellation desynchronizes bookings and availability**. The request-list Cancel action allows an approved request to become cancelled while its rental stays payment-pending and payable. Its dates become available, no fee is recorded and no owner notification is sent. Route approved requests through the booking cancellation operation and reject payments on cancelled requests.
2. **Cancellation remains possible after recorded handover**. The booking stays Paid until both pickup acknowledgements, so owner handover alone does not stop cancellation. Check the handover timestamp, not only the combined rental status.
3. **Participant authorization for reviews is incomplete**. The owner can open the renter rental component and submit a renter-to-owner review. Enforce the renter identity in each renter-only action; do not rely on general participant view authorization.
4. **The login model contradicts the baseline**. Add a selected Owner/Renter session role, role-specific navigation and selected-role access restrictions, or formally revise the baseline to approve the merged member interface.
5. **Terms and notifications are incomplete**. Require both recorded acceptance timestamps before booking; send booking/status notifications to both parties. Preserve the agreed terms/policy if they can change later.
6. **Historical records are fragile**. Deleted listings disappear from normal rental/request relationships, and account deletion cascades into counterpart transaction records. Preserve historical references or snapshots and define retention behavior.
7. **Financial and reservation displays are incomplete**. Provide the owner receipt, reconcile cancelled/refunded earnings, and implement the date-specific Reserved/availability behavior required after payment.
8. **Complete remaining user and operational features**. Add public received-review profiles, pin photos, real-time refresh, admin transaction management, and email/scheduler configuration; then validate browser, load and deployment behavior.

The missing live payment gateway is not a defect against this baseline: the document explicitly excludes that integration. The current supported payment flow is simulated and must not be described as processing real funds. Courier integration is likewise outside scope.

## Functional requirements status matrix

| ID | Requirement or flow | Status |
| --- | --- | --- |
| [FR-01](#fr-01) | Registration as Owner and/or Renter | **Implemented** |
| [FR-02](#fr-02) | Email verification before full access | **Partial** |
| [FR-03](#fr-03) | Login and logout with role selection at every login | **Partial** |
| [FR-04](#fr-04) | Forgotten password recovery through verified email | **Partial** |
| [FR-05](#fr-05) | Create an item listing | **Implemented** |
| [FR-06](#fr-06) | Upload one or more item photos | **Implemented** |
| [FR-07](#fr-07) | Edit pause or remove a listing | **Partial** |
| [FR-08](#fr-08) | Update item availability status | **Partial** |
| [FR-09](#fr-09) | Search and filter available items | **Implemented** |
| [FR-10](#fr-10) | Complete item details and availability calendar | **Implemented** |
| [FR-11](#fr-11) | Request an available item for a rental period | **Partial** |
| [FR-12](#fr-12) | Owner reviews approves or declines requests | **Implemented** |
| [FR-13](#fr-13) | Notify both parties of request status | **Partial** |
| [FR-14](#fr-14) | Confirmed booking after approval and accepted terms | **Partial** |
| [FR-15](#fr-15) | Both parties accept terms including cancellation policy | **Partial** |
| [FR-16](#fr-16) | Automatically calculate rental cost | **Implemented** |
| [FR-17](#fr-17) | Record payments and generate receipts for both parties | **Partial** |
| [FR-18](#fr-18) | Coordinate and confirm pickup or delivery | **Implemented** |
| [FR-19](#fr-19) | Display real-time ongoing due-soon and overdue status | **Partial** |
| [FR-20](#fr-20) | Automated due-date and overdue notifications | **Partial** |
| [FR-21](#fr-21) | Owner confirms return and records returned condition | **Implemented** |
| [FR-22](#fr-22) | Close and archive confirmed returns | **Implemented** |
| [FR-23](#fr-23) | Both parties rate and review each other after completion | **Partial** |
| [FR-24](#fr-24) | Complete booking and transaction history | **Partial** |
| [FR-25](#fr-25) | Owner earnings and completed transactions summary | **Partial** |
| [FR-26](#fr-26) | Admin manages and suspends user accounts | **Implemented** |
| [FR-27](#fr-27) | Admin monitors and moderates listings | **Implemented** |
| [FR-28](#fr-28) | Admin views and manages all rental transactions | **Partial** |
| [FR-29](#fr-29) | Owner or renter files transaction report or dispute | **Implemented** |
| [FR-30](#fr-30) | Admin reviews investigates and resolves disputes | **Implemented** |
| [FR-31](#fr-31) | Direct messaging about a listing or transaction | **Partial** |
| [FR-32](#fr-32) | Conversation threads and new-message notifications | **Implemented** |
| [FR-33](#fr-33) | Complete message history with another participant | **Implemented** |
| [FR-34](#fr-34) | Cancellation only before handover | **Partial** |
| [FR-35](#fr-35) | Calculate and apply timing-based cancellation fees | **Partial** |
| [FR-36](#fr-36) | Cancellation notifies owner and updates availability | **Partial** |
| [FR-37](#fr-37) | Cancellation reason and fees in both parties history | **Partial** |
| [FR-38](#fr-38) | Update picture name address email password and phone | **Implemented** |
| [FR-39](#fr-39) | Secure admin login and logout | **Implemented** |
| [FR-40](#fr-40) | Booking confirmation notifications to both parties | **Partial** |
| [FR-41](#fr-41) | Renter pays a confirmed booking using supported method | **Implemented** |
| [FR-42](#fr-42) | Item becomes Reserved after payment for booking dates | **Partial** |
| [FR-43](#fr-43) | Owner logs handover and renter logs receipt | **Implemented** |
| [FR-44](#fr-44) | Rental becomes ongoing after handover | **Implemented** |
| [FR-45](#fr-45) | Prompt both parties and publish reviews on public profiles | **Partial** |
| [FR-46](#fr-46) | Notify involved parties of dispute resolution | **Implemented** |
| [FR-47](#fr-47) | Owner sets listing location for map | **Implemented** |
| [FR-48](#fr-48) | Available listings as interactive map pins | **Implemented** |
| [FR-49](#fr-49) | Permission-based location centers map and marks renter | **Implemented** |
| [FR-50](#fr-50) | Selected distance radius filters map listings | **Partial** |
| [FR-51](#fr-51) | Automatic nearest-to-farthest sorting | **Partial** |
| [FR-52](#fr-52) | Map keyword and category filtering matches browse | **Implemented** |
| [FR-53](#fr-53) | Map pin photo name price preview and detail opening | **Partial** |

## Functional requirements findings

### FR-01

**Registration as Owner and/or Renter — Implemented**

Requirement in the document:

> The system shall allow individuals to register an account as an Owner and/or a Renter using their name, email address, and password.

Name, unique email and password registration creates a member account that can list and rent. This satisfies dual capability; role selection is separately assessed under FR-03.

**Evidence:** [resources/views/livewire/pages/auth/register.blade.php](C:/Users/djani/lendly/resources/views/livewire/pages/auth/register.blade.php); [app/Models/User.php](C:/Users/djani/lendly/app/Models/User.php).

### FR-02

**Email verification before full access — Partial**

Requirement in the document:

> The system shall verify a new account via an email confirmation link or code before granting full access.

Signed email verification and verified-route restrictions exist, but this workspace uses MAIL_MAILER=log, so confirmation messages are logged rather than delivered to users.

**Evidence:** [app/Models/User.php](C:/Users/djani/lendly/app/Models/User.php); [routes/auth.php](C:/Users/djani/lendly/routes/auth.php); [routes/web.php](C:/Users/djani/lendly/routes/web.php); [config/mail.php](C:/Users/djani/lendly/config/mail.php); .env (mailer setting only).

### FR-03

**Login and logout with role selection at every login — Partial**

Requirement in the document:

> The system shall allow registered users to securely log in and log out using valid credentials, and shall require them to select at each login whether to enter as an Owner or as a Renter, giving access only to the interface and modules of the selected role.

Authentication, logout, throttling and session regeneration exist. Owner/Renter selection is absent; members can access both areas and see a merged dashboard. The existing role test explicitly expects this contrary behavior.

**Evidence:** [resources/views/livewire/pages/auth/login.blade.php](C:/Users/djani/lendly/resources/views/livewire/pages/auth/login.blade.php); [app/Livewire/Forms/LoginForm.php](C:/Users/djani/lendly/app/Livewire/Forms/LoginForm.php); [app/Models/User.php](C:/Users/djani/lendly/app/Models/User.php); [app/Livewire/Member/Dashboard.php](C:/Users/djani/lendly/app/Livewire/Member/Dashboard.php); [tests/Feature/RoleAccessTest.php](C:/Users/djani/lendly/tests/Feature/RoleAccessTest.php).

### FR-04

**Forgotten password recovery through verified email — Partial**

Requirement in the document:

> The system shall allow users to recover or reset a forgotten password through a verified email process.

Token-based email password reset is implemented and tested, but the current log mailer does not deliver the recovery message. Actual inbox delivery remains unverified.

**Evidence:** [resources/views/livewire/pages/auth/forgot-password.blade.php](C:/Users/djani/lendly/resources/views/livewire/pages/auth/forgot-password.blade.php); [resources/views/livewire/pages/auth/reset-password.blade.php](C:/Users/djani/lendly/resources/views/livewire/pages/auth/reset-password.blade.php); [tests/Feature/Auth/PasswordResetTest.php](C:/Users/djani/lendly/tests/Feature/Auth/PasswordResetTest.php).

### FR-05

**Create an item listing — Implemented**

Requirement in the document:

> The system shall allow owners to create an item listing with name, category, description, rental price, availability, condition, etc.

The owner form stores name, category, description, condition, rates, location, fulfillment and availability. Listings enter admin approval rather than publishing immediately; that is a B.2 process deviation.

**Evidence:** [app/Livewire/Owner/Listings/Form.php](C:/Users/djani/lendly/app/Livewire/Owner/Listings/Form.php); [tests/Feature/ListingTest.php](C:/Users/djani/lendly/tests/Feature/ListingTest.php).

### FR-06

**Upload one or more item photos — Implemented**

Requirement in the document:

> The system shall allow owners to upload one or more photos for each item listing.

Multiple listing images can be uploaded and stored; image type and size are validated. Photos are optional, so B.2 does not enforce at least one image.

**Evidence:** [app/Livewire/Owner/Listings/Form.php](C:/Users/djani/lendly/app/Livewire/Owner/Listings/Form.php); [app/Livewire/Owner/Listings/Form.php](C:/Users/djani/lendly/app/Livewire/Owner/Listings/Form.php); [tests/Feature/ListingTest.php](C:/Users/djani/lendly/tests/Feature/ListingTest.php).

### FR-07

**Edit pause or remove a listing — Partial**

Requirement in the document:

> The system shall allow owners to edit, pause, or remove an existing item listing.

Editing, pausing, reactivation and soft deletion exist. After deletion, historical rentals and requests lose their normal listing relationship; audit probe confirmed this. Historical pages dereference that missing relationship.

**Evidence:** [app/Livewire/Owner/Listings/Index.php](C:/Users/djani/lendly/app/Livewire/Owner/Listings/Index.php); [app/Livewire/Owner/Listings/Index.php](C:/Users/djani/lendly/app/Livewire/Owner/Listings/Index.php); [app/Models/Rental.php](C:/Users/djani/lendly/app/Models/Rental.php); [app/Models/RentalRequest.php](C:/Users/djani/lendly/app/Models/RentalRequest.php).

### FR-08

**Update item availability status — Partial**

Requirement in the document:

> The system shall allow owners to update an item’s availability status (e.g., Available, Rented, Unavailable).

Owners can toggle a boolean availability flag and pause publication. Available, Reserved and Rented are not modeled as item availability states; payment and handover do not update an item state.

**Evidence:** [app/Livewire/Owner/Listings/Form.php](C:/Users/djani/lendly/app/Livewire/Owner/Listings/Form.php); [app/Models/Listing.php](C:/Users/djani/lendly/app/Models/Listing.php); [app/Enums/ListingStatus.php](C:/Users/djani/lendly/app/Enums/ListingStatus.php).

### FR-09

**Search and filter available items — Implemented**

Requirement in the document:

> The system shall allow renters to search and filter available items by keyword, category, price range, etc.

Browse supports keyword, category/subcategory, price, condition, brand and sorting, and excludes unpublished or unavailable listings.

**Evidence:** [app/Livewire/Listings/Browse.php](C:/Users/djani/lendly/app/Livewire/Listings/Browse.php); [tests/Feature/ListingSearchTest.php](C:/Users/djani/lendly/tests/Feature/ListingSearchTest.php).

### FR-10

**Complete item details and availability calendar — Implemented**

Requirement in the document:

> The system shall display complete item details, including photos, description, rental rate, availability calendar, and owner rating.

Details display images, description, rates, owner rating and a calendar based on approved booking ranges.

**Evidence:** [app/Livewire/Listings/Show.php](C:/Users/djani/lendly/app/Livewire/Listings/Show.php); [resources/views/livewire/listings/show.blade.php](C:/Users/djani/lendly/resources/views/livewire/listings/show.blade.php); [resources/js/calendar.js](C:/Users/djani/lendly/resources/js/calendar.js).

### FR-11

**Request an available item for a rental period — Partial**

Requirement in the document:

> The system shall allow a renter to submit a rental request for an available item, specifying the desired rental period.

Date, maximum-duration, fulfillment, self-rental and overlap checks exist. Availability is checked at initial mount only; a request can still be submitted after the owner makes the item unavailable, confirmed by an audit probe.

**Evidence:** [app/Livewire/RentalRequests/Create.php](C:/Users/djani/lendly/app/Livewire/RentalRequests/Create.php); [app/Livewire/RentalRequests/Create.php](C:/Users/djani/lendly/app/Livewire/RentalRequests/Create.php).

### FR-12

**Owner reviews approves or declines requests — Implemented**

Requirement in the document:

> The system shall allow an owner to review, approve, or decline an incoming rental request.

Owners can review their requests, approve with terms acceptance, or reject with a reason. Ownership authorization and an overlap check exist. Concurrent approvals remain a NFR-01/03 risk.

**Evidence:** [app/Livewire/Owner/RentalRequests/Index.php](C:/Users/djani/lendly/app/Livewire/Owner/RentalRequests/Index.php); [app/Livewire/Owner/RentalRequests/Index.php](C:/Users/djani/lendly/app/Livewire/Owner/RentalRequests/Index.php); [tests/Feature/RentalRequestTest.php](C:/Users/djani/lendly/tests/Feature/RentalRequestTest.php).

### FR-13

**Notify both parties of request status — Partial**

Requirement in the document:

> The system shall notify both parties of the current status of a rental request (Pending, Approved, or Declined).

Submission notifies the owner, and approval or rejection notifies the renter. The owner receives no approval/decline status notification; pending status is shown in the UI rather than notified to both.

**Evidence:** [app/Livewire/RentalRequests/Create.php](C:/Users/djani/lendly/app/Livewire/RentalRequests/Create.php); [app/Livewire/Owner/RentalRequests/Index.php](C:/Users/djani/lendly/app/Livewire/Owner/RentalRequests/Index.php); [app/Livewire/Owner/RentalRequests/Index.php](C:/Users/djani/lendly/app/Livewire/Owner/RentalRequests/Index.php).

### FR-14

**Confirmed booking after approval and accepted terms — Partial**

Requirement in the document:

> The system shall generate a confirmed booking record once a rental request is approved and the rental terms are accepted.

Owner approval creates a booking awaiting payment, but approval does not require a recorded renter acceptance timestamp. A pending request without renter acceptance was successfully booked in an audit probe.

**Evidence:** [app/Livewire/Owner/RentalRequests/Index.php](C:/Users/djani/lendly/app/Livewire/Owner/RentalRequests/Index.php); [app/Livewire/Owner/RentalRequests/Index.php](C:/Users/djani/lendly/app/Livewire/Owner/RentalRequests/Index.php).

### FR-15

**Both parties accept terms including cancellation policy — Partial**

Requirement in the document:

> The system shall require both the owner and renter to accept the rental terms and agreement, which shall include the applicable cancellation policy, cancellation fees, and other details before a booking is finalized.

Both normal forms require acceptance and display cancellation rules. The approval action checks the owner's checkbox but never verifies renter_terms_accepted_at. Acceptance also occurs before/during approval rather than after approval as B.4 describes.

**Evidence:** [resources/views/livewire/rental-requests/create.blade.php](C:/Users/djani/lendly/resources/views/livewire/rental-requests/create.blade.php); [resources/views/livewire/owner/rental-requests/index.blade.php](C:/Users/djani/lendly/resources/views/livewire/owner/rental-requests/index.blade.php); [app/Livewire/Owner/RentalRequests/Index.php](C:/Users/djani/lendly/app/Livewire/Owner/RentalRequests/Index.php).

### FR-16

**Automatically calculate rental cost — Implemented**

Requirement in the document:

> The system shall automatically compute the total rental cost based on the rental duration and the item’s rate.

Inclusive rental days multiplied by the daily rate determine rental fees; commission and deposit are separately added and amounts rounded. Existing tests cover the calculation.

**Evidence:** [app/Livewire/RentalRequests/Create.php](C:/Users/djani/lendly/app/Livewire/RentalRequests/Create.php); [app/Livewire/RentalRequests/Create.php](C:/Users/djani/lendly/app/Livewire/RentalRequests/Create.php); [app/Livewire/RentalRequests/Create.php](C:/Users/djani/lendly/app/Livewire/RentalRequests/Create.php); [tests/Feature/PaymentTest.php](C:/Users/djani/lendly/tests/Feature/PaymentTest.php).

### FR-17

**Record payments and generate receipts for both parties — Partial**

Requirement in the document:

> The system shall record every payment transaction and generate a digital receipt for both parties.

Payment records and a renter receipt with transaction reference exist. The owner page displays an earnings breakdown, without the payment receipt or transaction reference. The existing test named for both-party receipts only asserts the owner's earnings label.

**Evidence:** [app/Livewire/Renter/Rentals/Show.php](C:/Users/djani/lendly/app/Livewire/Renter/Rentals/Show.php); [resources/views/livewire/renter/rentals/show.blade.php](C:/Users/djani/lendly/resources/views/livewire/renter/rentals/show.blade.php); [resources/views/livewire/owner/rentals/show.blade.php](C:/Users/djani/lendly/resources/views/livewire/owner/rentals/show.blade.php); [tests/Feature/PaymentTest.php](C:/Users/djani/lendly/tests/Feature/PaymentTest.php).

### FR-18

**Coordinate and confirm pickup or delivery — Implemented**

Requirement in the document:

> The system shall allow the owner and renter to coordinate and confirm item pickup or delivery arrangements.

A fulfillment method is chosen during requesting; both participants can coordinate in the request chat and record pickup confirmations. Courier integration is explicitly outside scope. Automated schedule notices are assessed under B.6.

**Evidence:** [app/Livewire/RentalRequests/Create.php](C:/Users/djani/lendly/app/Livewire/RentalRequests/Create.php); [app/Livewire/Messages/Show.php](C:/Users/djani/lendly/app/Livewire/Messages/Show.php); [app/Services/RentalLifecycle.php](C:/Users/djani/lendly/app/Services/RentalLifecycle.php).

### FR-19

**Display real-time ongoing due-soon and overdue status — Partial**

Requirement in the document:

> The system shall track and display the real-time status of an active rental (e.g., Ongoing, Due Soon, Overdue).

Active Rental, derived Due Soon and Overdue states are displayed. Open screens do not poll or subscribe for another participant's changes; overdue updates depend on the daily scheduler, so real-time synchronization is incomplete.

**Evidence:** [app/Models/Rental.php](C:/Users/djani/lendly/app/Models/Rental.php); [app/Enums/RentalStatus.php](C:/Users/djani/lendly/app/Enums/RentalStatus.php); [app/Services/RentalLifecycle.php](C:/Users/djani/lendly/app/Services/RentalLifecycle.php); [routes/console.php](C:/Users/djani/lendly/routes/console.php).

### FR-20

**Automated due-date and overdue notifications — Partial**

Requirement in the document:

> The system shall send automated notifications for upcoming due dates and overdue rentals.

Daily reminder and overdue commands exist and have tests. A running scheduler on the deployed server is not evidenced, and the daily cadence is the only automation configured.

**Evidence:** [app/Services/RentalLifecycle.php](C:/Users/djani/lendly/app/Services/RentalLifecycle.php); [app/Services/RentalLifecycle.php](C:/Users/djani/lendly/app/Services/RentalLifecycle.php); [routes/console.php](C:/Users/djani/lendly/routes/console.php); [tests/Feature/NotificationsAndChatTest.php](C:/Users/djani/lendly/tests/Feature/NotificationsAndChatTest.php).

### FR-21

**Owner confirms return and records returned condition — Implemented**

Requirement in the document:

> The system shall allow the owner to confirm the return of a rented item and record its returned condition.

Owner return confirmation, condition notes, images and damage details exist. Both parties must confirm return before condition recording, which adds a dependency beyond B.7.

**Evidence:** [app/Livewire/Owner/Rentals/Show.php](C:/Users/djani/lendly/app/Livewire/Owner/Rentals/Show.php); [app/Livewire/Owner/Rentals/Show.php](C:/Users/djani/lendly/app/Livewire/Owner/Rentals/Show.php); [app/Policies/RentalPolicy.php](C:/Users/djani/lendly/app/Policies/RentalPolicy.php); [tests/Feature/ConditionAndDamageTest.php](C:/Users/djani/lendly/tests/Feature/ConditionAndDamageTest.php).

### FR-22

**Close and archive confirmed returns — Implemented**

Requirement in the document:

> The system shall close and archive a rental transaction once the return has been confirmed.

After both return confirmations and an after-condition record, the owner completes inspection; status and completed timestamp are stored and the rental remains in history. A separate archival store is not required by the document.

**Evidence:** [app/Services/RentalLifecycle.php](C:/Users/djani/lendly/app/Services/RentalLifecycle.php); [app/Services/RentalLifecycle.php](C:/Users/djani/lendly/app/Services/RentalLifecycle.php); [app/Policies/RentalPolicy.php](C:/Users/djani/lendly/app/Policies/RentalPolicy.php); [tests/Feature/ConditionAndDamageTest.php](C:/Users/djani/lendly/tests/Feature/ConditionAndDamageTest.php).

### FR-23

**Both parties rate and review each other after completion — Partial**

Requirement in the document:

> The system shall allow the owner and renter to rate and review each other after a completed transaction.

Post-completion rating/comment forms and duplicate prevention exist. Renter review actions lack a renter-id check; the owner can use the renter component to submit a renter-to-owner review of themselves, confirmed by an audit probe.

**Evidence:** [app/Livewire/Renter/Rentals/Show.php](C:/Users/djani/lendly/app/Livewire/Renter/Rentals/Show.php); [app/Livewire/Owner/Rentals/Show.php](C:/Users/djani/lendly/app/Livewire/Owner/Rentals/Show.php); [app/Policies/RentalPolicy.php](C:/Users/djani/lendly/app/Policies/RentalPolicy.php).

### FR-24

**Complete booking and transaction history — Partial**

Requirement in the document:

> The system shall allow users to view their complete history of past bookings and transactions.

Role-specific rental lists include completed and cancelled bookings, and requests have their own list. Deleted listings lose their historical relationship; account deletion also cascades through rental/payment records, compromising retained counterpart history.

**Evidence:** [app/Livewire/Owner/Rentals/Index.php](C:/Users/djani/lendly/app/Livewire/Owner/Rentals/Index.php); [app/Livewire/Renter/Rentals/Index.php](C:/Users/djani/lendly/app/Livewire/Renter/Rentals/Index.php); [app/Models/Rental.php](C:/Users/djani/lendly/app/Models/Rental.php); [resources/views/livewire/profile/delete-user-form.blade.php](C:/Users/djani/lendly/resources/views/livewire/profile/delete-user-form.blade.php); [database/migrations/2026_09_06_171728_a_create_rentals_table.php](C:/Users/djani/lendly/database/migrations/2026_09_06_171728_a_create_rentals_table.php).

### FR-25

**Owner earnings and completed transactions summary — Partial**

Requirement in the document:

> The system shall allow owners to view a summary of their earnings and completed rental transactions.

Dashboard earnings, monthly charts and completed counts exist. Earnings include every rental with paid_at, including subsequently cancelled rentals, and do not reconcile refunded rentals or cancellation fees.

**Evidence:** [app/Livewire/Member/Dashboard.php](C:/Users/djani/lendly/app/Livewire/Member/Dashboard.php); [app/Livewire/Member/Dashboard.php](C:/Users/djani/lendly/app/Livewire/Member/Dashboard.php); [resources/views/livewire/owner/rentals/show.blade.php](C:/Users/djani/lendly/resources/views/livewire/owner/rentals/show.blade.php).

### FR-26

**Admin manages and suspends user accounts — Implemented**

Requirement in the document:

> The system shall allow the admin to view, manage, and suspend user accounts when necessary.

Admin dashboard lists/searches users and supports suspension/reactivation; suspended sessions and login are blocked, and admin accounts are protected from suspension.

**Evidence:** [app/Livewire/Admin/Dashboard.php](C:/Users/djani/lendly/app/Livewire/Admin/Dashboard.php); [app/Http/Middleware/EnsureAccountIsActive.php](C:/Users/djani/lendly/app/Http/Middleware/EnsureAccountIsActive.php); [tests/Feature/RoleAccessTest.php](C:/Users/djani/lendly/tests/Feature/RoleAccessTest.php).

### FR-27

**Admin monitors and moderates listings — Implemented**

Requirement in the document:

> The system shall allow the admin to monitor and moderate item listings for policy compliance.

Admin can filter listings, approve, reject with a reason, or deactivate. Owner creation and edits are submitted for moderation.

**Evidence:** [app/Livewire/Admin/Listings/Index.php](C:/Users/djani/lendly/app/Livewire/Admin/Listings/Index.php); [app/Livewire/Owner/Listings/Form.php](C:/Users/djani/lendly/app/Livewire/Owner/Listings/Form.php); [tests/Feature/ListingTest.php](C:/Users/djani/lendly/tests/Feature/ListingTest.php).

### FR-28

**Admin views and manages all rental transactions — Partial**

Requirement in the document:

> The system shall allow the admin to view and manage all rental transactions on the platform.

Admin transaction listing supports search, status filters and totals. The component contains no transaction management actions or complete detail/receipt investigation view; the admin rental policy permits viewing but the member routes exclude admin.

**Evidence:** [app/Livewire/Admin/Rentals/Index.php](C:/Users/djani/lendly/app/Livewire/Admin/Rentals/Index.php); [routes/web.php](C:/Users/djani/lendly/routes/web.php); [tests/Feature/AdminTransactionsTest.php](C:/Users/djani/lendly/tests/Feature/AdminTransactionsTest.php).

### FR-29

**Owner or renter files transaction report or dispute — Implemented**

Requirement in the document:

> The system shall allow owners and renters to file a report or dispute regarding a rental transaction.

Both parties can submit a transaction dispute with reason and description; damage claims also support renter disagreement. Standalone listing/user reports and general evidence attachments are absent, assessed as B.9 gaps.

**Evidence:** [app/Livewire/Owner/Rentals/Show.php](C:/Users/djani/lendly/app/Livewire/Owner/Rentals/Show.php); [app/Livewire/Renter/Rentals/Show.php](C:/Users/djani/lendly/app/Livewire/Renter/Rentals/Show.php); [tests/Feature/ReviewsAndDisputeTest.php](C:/Users/djani/lendly/tests/Feature/ReviewsAndDisputeTest.php).

### FR-30

**Admin reviews investigates and resolves disputes — Implemented**

Requirement in the document:

> The system shall provide the admin with tools to review, investigate, and resolve reported disputes.

Admin can review descriptions, participants, linked damage evidence and resolve with notes. Damage resolutions update claim/deposit records; general refund decisions are recommendations without automatic money movement, consistent with gateway exclusion.

**Evidence:** [app/Livewire/Admin/Disputes/Index.php](C:/Users/djani/lendly/app/Livewire/Admin/Disputes/Index.php); [resources/views/livewire/admin/disputes/index.blade.php](C:/Users/djani/lendly/resources/views/livewire/admin/disputes/index.blade.php); [app/Services/RentalLifecycle.php](C:/Users/djani/lendly/app/Services/RentalLifecycle.php); [tests/Feature/ReviewsAndDisputeTest.php](C:/Users/djani/lendly/tests/Feature/ReviewsAndDisputeTest.php).

### FR-31

**Direct messaging about a listing or transaction — Partial**

Requirement in the document:

> The system shall allow a renter and an owner to send and receive direct messages with each other regarding an item listing or rental transaction.

Participant messaging works after a rental request exists. There is no listing-only inquiry or way to start a chat before making a request.

**Evidence:** [routes/web.php](C:/Users/djani/lendly/routes/web.php); [app/Livewire/Messages/Show.php](C:/Users/djani/lendly/app/Livewire/Messages/Show.php); [app/Models/Message.php](C:/Users/djani/lendly/app/Models/Message.php).

### FR-32

**Conversation threads and new-message notifications — Implemented**

Requirement in the document:

> The system shall organize messages between an owner and a renter into a conversation thread and notify the recipient of new incoming messages.

Messages belong to request threads and each send stores a recipient notification. Inbox previews, unread counts and read marks exist; instant delivery to an already open screen is not implemented.

**Evidence:** [app/Livewire/Messages/Show.php](C:/Users/djani/lendly/app/Livewire/Messages/Show.php); [app/Livewire/Messages/Concerns/ListsThreads.php](C:/Users/djani/lendly/app/Livewire/Messages/Concerns/ListsThreads.php); [tests/Feature/NotificationsAndChatTest.php](C:/Users/djani/lendly/tests/Feature/NotificationsAndChatTest.php); [tests/Feature/MessagesInboxTest.php](C:/Users/djani/lendly/tests/Feature/MessagesInboxTest.php).

### FR-33

**Complete message history with another participant — Implemented**

Requirement in the document:

> The system shall allow users to view their complete message history with a specific owner or renter.

Each request thread retrieves all its messages; users can open historical conversations from the inbox. Conversations are organized per rental request rather than combined per person.

**Evidence:** [app/Models/RentalRequest.php](C:/Users/djani/lendly/app/Models/RentalRequest.php); [app/Livewire/Messages/Show.php](C:/Users/djani/lendly/app/Livewire/Messages/Show.php); [resources/views/livewire/messages/show.blade.php](C:/Users/djani/lendly/resources/views/livewire/messages/show.blade.php).

### FR-34

**Cancellation only before handover — Partial**

Requirement in the document:

> The system shall allow a renter to cancel a rental request or a confirmed booking prior to item hand-over, subject to the cancellation policy stated in the rental terms and agreement.

Booking cancellation is available in payment-pending/paid states, but it remains available after the owner has recorded handover if the renter has not confirmed receipt. The request-list cancellation action also cancels approved requests without cancelling their booking.

**Evidence:** [app/Models/Rental.php](C:/Users/djani/lendly/app/Models/Rental.php); [app/Models/RentalRequest.php](C:/Users/djani/lendly/app/Models/RentalRequest.php); [app/Livewire/Renter/RentalRequests/Index.php](C:/Users/djani/lendly/app/Livewire/Renter/RentalRequests/Index.php); [app/Services/RentalLifecycle.php](C:/Users/djani/lendly/app/Services/RentalLifecycle.php).

### FR-35

**Calculate and apply timing-based cancellation fees — Partial**

Requirement in the document:

> The system shall automatically compute and apply a cancellation fee, if applicable, based on the timing of the cancellation relative to the scheduled rental start date, in accordance with the rental terms and agreement.

The booking cancellation path computes 20% within 48 hours and records the fee. Approved-request cancellation bypasses this path and records no fee. Fee settlement/payment adjustments are not recorded separately.

**Evidence:** [app/Services/CancellationPolicy.php](C:/Users/djani/lendly/app/Services/CancellationPolicy.php); [app/Services/RentalLifecycle.php](C:/Users/djani/lendly/app/Services/RentalLifecycle.php); [app/Livewire/Renter/RentalRequests/Index.php](C:/Users/djani/lendly/app/Livewire/Renter/RentalRequests/Index.php).

### FR-36

**Cancellation notifies owner and updates availability — Partial**

Requirement in the document:

> The system shall notify the owner once a rental has been cancelled and update the item’s availability status accordingly.

Booking cancellation notifies the owner and releases approved date overlap. Request-list cancellation does not notify the owner or synchronize the booking; no explicit item availability-state update occurs.

**Evidence:** [app/Services/RentalLifecycle.php](C:/Users/djani/lendly/app/Services/RentalLifecycle.php); [app/Services/RentalLifecycle.php](C:/Users/djani/lendly/app/Services/RentalLifecycle.php); [app/Livewire/Renter/RentalRequests/Index.php](C:/Users/djani/lendly/app/Livewire/Renter/RentalRequests/Index.php).

### FR-37

**Cancellation reason and fees in both parties history — Partial**

Requirement in the document:

> The system shall record the cancellation, including the reason (if provided) and any cancellation fee applied, in the rental history of both parties.

Booking cancellation persists reason, timestamp and fee for both rental views. Request cancellations persist only the request status, without cancellation metadata or corresponding booking-history update.

**Evidence:** [app/Services/RentalLifecycle.php](C:/Users/djani/lendly/app/Services/RentalLifecycle.php); [resources/views/livewire/owner/rentals/show.blade.php](C:/Users/djani/lendly/resources/views/livewire/owner/rentals/show.blade.php); [app/Livewire/Renter/RentalRequests/Index.php](C:/Users/djani/lendly/app/Livewire/Renter/RentalRequests/Index.php).

### FR-38

**Update picture name address email password and phone — Implemented**

Requirement in the document:

> The system shall allow users to view and update their personal information, including their picture, name, address, email, password, phone number, etc.

Profile forms support all listed fields, avatar upload/removal and password updates. Changing email invalidates its verified flag. Existing tests cover name/email, phone/address and avatar behavior.

**Evidence:** [resources/views/livewire/profile/update-profile-information-form.blade.php](C:/Users/djani/lendly/resources/views/livewire/profile/update-profile-information-form.blade.php); [resources/views/livewire/profile/update-password-form.blade.php](C:/Users/djani/lendly/resources/views/livewire/profile/update-password-form.blade.php); [tests/Feature/ProfileTest.php](C:/Users/djani/lendly/tests/Feature/ProfileTest.php).

### FR-39

**Secure admin login and logout — Implemented**

Requirement in the document:

> The system shall allow the admin to securely log in and log out using valid administrator credentials.

The shared login validates administrator credentials and redirects admins to their dashboard. Admin/member route separation, rate limits, suspension checks and logout session invalidation are present.

**Evidence:** [app/Livewire/Forms/LoginForm.php](C:/Users/djani/lendly/app/Livewire/Forms/LoginForm.php); [app/Models/User.php](C:/Users/djani/lendly/app/Models/User.php); [app/Livewire/Actions/Logout.php](C:/Users/djani/lendly/app/Livewire/Actions/Logout.php); [routes/web.php](C:/Users/djani/lendly/routes/web.php).

### FR-40

**Booking confirmation notifications to both parties — Partial**

Requirement in the document:

> The system shall send a booking confirmation notification to both the owner and the renter once a booking is confirmed.

Approval sends a confirmation/approved notification to the renter only. There is no corresponding owner booking confirmation; an audit probe confirmed zero owner notifications after approval.

**Evidence:** [app/Livewire/Owner/RentalRequests/Index.php](C:/Users/djani/lendly/app/Livewire/Owner/RentalRequests/Index.php).

### FR-41

**Renter pays a confirmed booking using supported method — Implemented**

Requirement in the document:

> The system shall allow the renter to complete payment for a confirmed booking through the platform’s supported payment method.

The declared supported method is a clearly labelled simulated payment. It creates payment/deposit records and marks the booking paid. This meets the document's payment-recording scope; real collection is not implemented and live gateways are explicitly excluded.

**Evidence:** [app/Livewire/Renter/Rentals/Show.php](C:/Users/djani/lendly/app/Livewire/Renter/Rentals/Show.php); [resources/views/livewire/renter/rentals/show.blade.php](C:/Users/djani/lendly/resources/views/livewire/renter/rentals/show.blade.php); [resources/views/livewire/renter/rentals/show.blade.php](C:/Users/djani/lendly/resources/views/livewire/renter/rentals/show.blade.php); [tests/Feature/PaymentTest.php](C:/Users/djani/lendly/tests/Feature/PaymentTest.php).

### FR-42

**Item becomes Reserved after payment for booking dates — Partial**

Requirement in the document:

> The system shall update the item’s status to “Reserved” for the duration of a confirmed booking once the payment transaction has been recorded.

Approved requests block overlapping dates, providing partial reservation behavior before payment. Payment sets rental status Paid, while item availability/status remains unchanged; no Reserved state is displayed or stored.

**Evidence:** [app/Models/Listing.php](C:/Users/djani/lendly/app/Models/Listing.php); [app/Livewire/Renter/Rentals/Show.php](C:/Users/djani/lendly/app/Livewire/Renter/Rentals/Show.php); [app/Enums/ListingStatus.php](C:/Users/djani/lendly/app/Enums/ListingStatus.php).

### FR-43

**Owner logs handover and renter logs receipt — Implemented**

Requirement in the document:

> The system shall allow the owner to mark an item as “Handed Over” and the renter to mark the item as “Received” once the item has been released and successfully received, respectively.

Independent owner/renter pickup timestamps and buttons record the two acknowledgements. They are labelled pickup confirmations rather than the document's exact Handed Over/Received wording.

**Evidence:** [app/Services/RentalLifecycle.php](C:/Users/djani/lendly/app/Services/RentalLifecycle.php); [app/Policies/RentalPolicy.php](C:/Users/djani/lendly/app/Policies/RentalPolicy.php); [tests/Feature/RentalLifecycleTest.php](C:/Users/djani/lendly/tests/Feature/RentalLifecycleTest.php).

### FR-44

**Rental becomes ongoing after handover — Implemented**

Requirement in the document:

> The system shall update the rental status to “Ongoing” once the hand-over of the item has been logged.

After both pickup acknowledgements the rental becomes Active, displayed as Active Rental, which is the implementation's equivalent of Ongoing. An owner-only handover does not activate it; clarify the requirement if one acknowledgement is intended.

**Evidence:** [app/Services/RentalLifecycle.php](C:/Users/djani/lendly/app/Services/RentalLifecycle.php); [app/Enums/RentalStatus.php](C:/Users/djani/lendly/app/Enums/RentalStatus.php); [tests/Feature/RentalLifecycleTest.php](C:/Users/djani/lendly/tests/Feature/RentalLifecycleTest.php).

### FR-45

**Prompt both parties and publish reviews on public profiles — Partial**

Requirement in the document:

> The system shall prompt the owner and renter to optionally rate and review each other once a rental transaction is closed, and shall publish each submitted rating and review to the respective user’s public profile.

Both rental detail pages have optional review forms and the renter receives a completion prompt. The owner receives no completion review notification, and there is no public user profile route publishing each user review.

**Evidence:** [app/Services/RentalLifecycle.php](C:/Users/djani/lendly/app/Services/RentalLifecycle.php); [resources/views/livewire/owner/rentals/show.blade.php](C:/Users/djani/lendly/resources/views/livewire/owner/rentals/show.blade.php); [resources/views/livewire/renter/rentals/show.blade.php](C:/Users/djani/lendly/resources/views/livewire/renter/rentals/show.blade.php); [routes/web.php](C:/Users/djani/lendly/routes/web.php).

### FR-46

**Notify involved parties of dispute resolution — Implemented**

Requirement in the document:

> The system shall notify the involved parties of the resolution once the admin closes a reported issue or dispute.

Resolving a dispute records the decision and sends database notifications to both renter and owner.

**Evidence:** [app/Services/RentalLifecycle.php](C:/Users/djani/lendly/app/Services/RentalLifecycle.php); [app/Services/RentalLifecycle.php](C:/Users/djani/lendly/app/Services/RentalLifecycle.php); [tests/Feature/ReviewsAndDisputeTest.php](C:/Users/djani/lendly/tests/Feature/ReviewsAndDisputeTest.php).

### FR-47

**Owner sets listing location for map — Implemented**

Requirement in the document:

> The system shall allow owners to set the location of an item listing so that the item can be shown on the map.

Address/location and optional latitude/longitude are supported; the map picker reports selected or permission-granted device coordinates.

**Evidence:** [app/Livewire/Owner/Listings/Form.php](C:/Users/djani/lendly/app/Livewire/Owner/Listings/Form.php); [resources/js/map.js](C:/Users/djani/lendly/resources/js/map.js); [resources/js/map.js](C:/Users/djani/lendly/resources/js/map.js).

### FR-48

**Available listings as interactive map pins — Implemented**

Requirement in the document:

> The system shall display available item listings as pins on an interactive map.

Leaflet displays published, available listings having coordinates as pins with interactive popups. Listings without coordinates remain available in the normal browse view.

**Evidence:** [app/Livewire/Listings/Map.php](C:/Users/djani/lendly/app/Livewire/Listings/Map.php); [resources/js/map.js](C:/Users/djani/lendly/resources/js/map.js); [tests/Feature/ListingSearchTest.php](C:/Users/djani/lendly/tests/Feature/ListingSearchTest.php).

### FR-49

**Permission-based location centers map and marks renter — Implemented**

Requirement in the document:

> The system shall allow the renter to use their current location, obtained through the browser with the user’s permission, to center the map and mark the renter’s own position.

Use my location invokes browser geolocation on user action, centers the map and places a position marker. Permission behavior uses the browser API; actual browser testing was not performed.

**Evidence:** [resources/js/map.js](C:/Users/djani/lendly/resources/js/map.js); [resources/views/livewire/listings/map.blade.php](C:/Users/djani/lendly/resources/views/livewire/listings/map.blade.php).

### FR-50

**Selected distance radius filters map listings — Partial**

Requirement in the document:

> The system shall allow the renter, once a location is set, to limit the displayed listings to a selected distance radius (e.g., 5, 10, 25, or 50 km).

5/10/25/50 km controls and distance filtering exist. Falsy coordinate checks treat a valid latitude or longitude of zero as absent, disabling the filter; zero latitude was confirmed in an audit probe.

**Evidence:** [app/Livewire/Listings/Map.php](C:/Users/djani/lendly/app/Livewire/Listings/Map.php); [app/Livewire/Listings/Map.php](C:/Users/djani/lendly/app/Livewire/Listings/Map.php); [resources/views/livewire/listings/map.blade.php](C:/Users/djani/lendly/resources/views/livewire/listings/map.blade.php).

### FR-51

**Automatic nearest-to-farthest sorting — Partial**

Requirement in the document:

> The system shall automatically sort listings from nearest to farthest once the renter’s location is set.

Listings are sorted by computed distance when both coordinates are truthy. Valid zero coordinates disable distance calculation and sorting.

**Evidence:** [app/Livewire/Listings/Map.php](C:/Users/djani/lendly/app/Livewire/Listings/Map.php); [app/Livewire/Listings/Map.php](C:/Users/djani/lendly/app/Livewire/Listings/Map.php).

### FR-52

**Map keyword and category filtering matches browse — Implemented**

Requirement in the document:

> The system shall allow the renter to search and filter the listings on the map by keyword and category, using the same filtering logic as the item search.

Both components use the same name/description/brand keyword predicates and category/subcategory matching. Their code is duplicated, which is a future maintenance concern rather than a current behavioral mismatch.

**Evidence:** [app/Livewire/Listings/Map.php](C:/Users/djani/lendly/app/Livewire/Listings/Map.php); [app/Livewire/Listings/Browse.php](C:/Users/djani/lendly/app/Livewire/Listings/Browse.php).

### FR-53

**Map pin photo name price preview and detail opening — Partial**

Requirement in the document:

> The system shall show, for each map pin, a preview containing the item’s photo, name, rental price per day, etc., and shall open the item’s detail page when the pin is selected.

Pins show name, daily price and distance with a View listing link. Image data is computed but never rendered in the popup. Pin selection opens the popup; another click follows the detail link.

**Evidence:** [app/Livewire/Listings/Map.php](C:/Users/djani/lendly/app/Livewire/Listings/Map.php); [resources/js/map.js](C:/Users/djani/lendly/resources/js/map.js).

## Non-functional requirements status matrix

| ID | Requirement or flow | Status |
| --- | --- | --- |
| [NFR-01](#nfr-01) | Accurate synchronized statuses bookings and history | **Partial** |
| [NFR-02](#nfr-02) | Accurate rental and cancellation costs | **Partial** |
| [NFR-03](#nfr-03) | Consistent availability bookings and payments across sessions | **Partial** |
| [NFR-04](#nfr-04) | Continuous availability and maintenance communication | **Unverified** |
| [NFR-05](#nfr-05) | Acceptable core-action response times | **Unverified** |
| [NFR-06](#nfr-06) | Secure password hashing | **Implemented** |
| [NFR-07](#nfr-07) | Role-based access protects sensitive data | **Partial** |
| [NFR-08](#nfr-08) | Consumer protection privacy and payment compliance | **Unverified** |
| [NFR-09](#nfr-09) | Simple intuitive listing and booking workflows | **Unverified** |
| [NFR-10](#nfr-10) | Clear specific actionable errors | **Partial** |
| [NFR-11](#nfr-11) | Modular maintainable implementation | **Implemented** |
| [NFR-12](#nfr-12) | Growth without major redesign | **Unverified** |
| [NFR-13](#nfr-13) | Automated backend frontend and manual validation | **Partial** |
| [NFR-14](#nfr-14) | Independently testable functions | **Implemented** |
| [NFR-15](#nfr-15) | Browser compatibility and device responsiveness | **Unverified** |
| [NFR-16](#nfr-16) | Email and map service interoperability | **Partial** |
| [NFR-17](#nfr-17) | Location only with user permission | **Implemented** |
| [NFR-18](#nfr-18) | Acceptable map pin and filter response times | **Unverified** |
| [NFR-19](#nfr-19) | Accurate distances and nearest-first order | **Partial** |
| [NFR-20](#nfr-20) | Browse and search without shared location | **Implemented** |

## Non-functional requirements findings

### NFR-01

**Accurate synchronized statuses bookings and history — Partial**

Requirement in the document:

> The system must ensure that item status, booking records, and transaction history remain accurate and synchronized at all times.

Confirmed approved-request cancellation leaves a payable booking while freeing its dates; deleted listing relations break historical references. Approval/payment/lifecycle writes lack a database transaction and booking/payment uniqueness safeguards, creating additional untested concurrency/partial-failure risks.

**Evidence:** [app/Livewire/Renter/RentalRequests/Index.php](C:/Users/djani/lendly/app/Livewire/Renter/RentalRequests/Index.php); [app/Livewire/Owner/RentalRequests/Index.php](C:/Users/djani/lendly/app/Livewire/Owner/RentalRequests/Index.php); [app/Livewire/Renter/Rentals/Show.php](C:/Users/djani/lendly/app/Livewire/Renter/Rentals/Show.php); [database/migrations/2026_09_06_171728_a_create_rentals_table.php](C:/Users/djani/lendly/database/migrations/2026_09_06_171728_a_create_rentals_table.php).

### NFR-02

**Accurate rental and cancellation costs — Partial**

Requirement in the document:

> The system must accurately compute the total rental cost and any applicable cancellation fee based on the rental duration, the item’s rate, and the rental terms and agreement.

Normal inclusive-day rental and timing-based fee calculations exist. The alternate approved-request cancellation path bypasses fees, so correctness is not consistent across all cancellation entry points.

**Evidence:** [app/Livewire/RentalRequests/Create.php](C:/Users/djani/lendly/app/Livewire/RentalRequests/Create.php); [app/Services/CancellationPolicy.php](C:/Users/djani/lendly/app/Services/CancellationPolicy.php); [app/Livewire/Renter/RentalRequests/Index.php](C:/Users/djani/lendly/app/Livewire/Renter/RentalRequests/Index.php).

### NFR-03

**Consistent availability bookings and payments across sessions — Partial**

Requirement in the document:

> The system must consistently and accurately reflect item availability, booking status, and payment records across all user sessions.

Records are database-backed, but approval/pay actions have unprotected read-then-write sequences; approved-request cancellation desynchronizes them. Open rental/message screens lack cross-session automatic refresh. Race behavior was identified from source rather than stress-tested.

**Evidence:** [app/Livewire/Owner/RentalRequests/Index.php](C:/Users/djani/lendly/app/Livewire/Owner/RentalRequests/Index.php); [app/Livewire/Renter/Rentals/Show.php](C:/Users/djani/lendly/app/Livewire/Renter/Rentals/Show.php); [resources/views/livewire/renter/rentals/show.blade.php](C:/Users/djani/lendly/resources/views/livewire/renter/rentals/show.blade.php); [resources/views/livewire/messages/show.blade.php](C:/Users/djani/lendly/resources/views/livewire/messages/show.blade.php).

### NFR-04

**Continuous availability and maintenance communication — Unverified**

Requirement in the document:

> The system should be accessible to users at all times, with any scheduled maintenance communicated to users in advance.

No uptime measurements, deployed hosting checks or advance maintenance communication workflow were available. A local codebase and health endpoint cannot establish continuous availability.

**Evidence:** [bootstrap/app.php](C:/Users/djani/lendly/bootstrap/app.php); repository infrastructure files.

### NFR-05

**Acceptable core-action response times — Unverified**

Requirement in the document:

> Core actions such as searching for items, submitting a rental request, and processing a booking must complete within an acceptable response time under normal load.

No normal-load definition, response-time target, benchmark or load-test result is provided. Functional tests do not establish response-time compliance.

**Evidence:** [tests/Feature/ListingSearchTest.php](C:/Users/djani/lendly/tests/Feature/ListingSearchTest.php); [tests/Feature/RentalRequestTest.php](C:/Users/djani/lendly/tests/Feature/RentalRequestTest.php); requirements NFR-05.

### NFR-06

**Secure password hashing — Implemented**

Requirement in the document:

> All user passwords must be stored using secure hashing algorithms.

Registration/reset use Hash and the User password cast is hashed; passwords are excluded from serialized attributes.

**Evidence:** [resources/views/livewire/pages/auth/register.blade.php](C:/Users/djani/lendly/resources/views/livewire/pages/auth/register.blade.php); [app/Models/User.php](C:/Users/djani/lendly/app/Models/User.php); [app/Models/User.php](C:/Users/djani/lendly/app/Models/User.php).

### NFR-07

**Role-based access protects sensitive data — Partial**

Requirement in the document:

> Sensitive data must be protected against unauthorized access through role-based access control for Owner, Renter, and Admin accounts.

Admin/member separation, participant ownership policies, suspension controls and verified access exist. Selected Owner/Renter session restriction is missing, and the owner can invoke renter review actions through an allowed participant-view route.

**Evidence:** [app/Http/Middleware/EnsureUserHasRole.php](C:/Users/djani/lendly/app/Http/Middleware/EnsureUserHasRole.php); [app/Models/User.php](C:/Users/djani/lendly/app/Models/User.php); [app/Policies/RentalPolicy.php](C:/Users/djani/lendly/app/Policies/RentalPolicy.php); [app/Livewire/Renter/Rentals/Show.php](C:/Users/djani/lendly/app/Livewire/Renter/Rentals/Show.php).

### NFR-08

**Consumer protection privacy and payment compliance — Unverified**

Requirement in the document:

> Rental agreements, data handling, and payment processing must align with applicable consumer protection and data privacy standards.

The platform displays basic terms and cancellation rules, but no named applicable standard, complete privacy notice, compliance assessment or legal acceptance evidence is provided. Technical features cannot certify this requirement.

**Evidence:** [resources/views/livewire/rental-requests/create.blade.php](C:/Users/djani/lendly/resources/views/livewire/rental-requests/create.blade.php); requirements NFR-08.

### NFR-09

**Simple intuitive listing and booking workflows — Unverified**

Requirement in the document:

> The system must provide a simple, intuitive interface that allows owners to list items and renters to complete a booking with minimal steps.

Forms, navigation, cost summaries and validation exist. No user usability results or definition of minimal steps is supplied; the role mismatch and workflow deviations need acceptance review.

**Evidence:** [resources/views/livewire/owner/listings/form.blade.php](C:/Users/djani/lendly/resources/views/livewire/owner/listings/form.blade.php); [resources/views/livewire/rental-requests/create.blade.php](C:/Users/djani/lendly/resources/views/livewire/rental-requests/create.blade.php); [resources/views/livewire/layout/sidebar.blade.php](C:/Users/djani/lendly/resources/views/livewire/layout/sidebar.blade.php).

### NFR-10

**Clear specific actionable errors — Partial**

Requirement in the document:

> Error messages displayed to users must be clear, specific, and actionable.

Field-level errors and helpful business-rule messages are common. Geolocation failure is silently swallowed, unsupported geolocation can leave the view's locating state unresolved, and broken history references may produce server errors.

**Evidence:** [app/Livewire/RentalRequests/Create.php](C:/Users/djani/lendly/app/Livewire/RentalRequests/Create.php); [app/Livewire/Owner/Listings/Form.php](C:/Users/djani/lendly/app/Livewire/Owner/Listings/Form.php); [resources/js/map.js](C:/Users/djani/lendly/resources/js/map.js).

### NFR-11

**Modular maintainable implementation — Implemented**

Requirement in the document:

> The system must be built in a modular manner to allow developers to easily update, patch, or extend features over time.

Models, enums, policies, middleware, Livewire modules, services, shared UI components and message-thread helpers separate responsibilities. Some transactional behavior is spread across components, but the required modular structure is present.

**Evidence:** [app/Services](C:/Users/djani/lendly/app/Services); [app/Policies](C:/Users/djani/lendly/app/Policies); [app/Http/Middleware](C:/Users/djani/lendly/app/Http/Middleware); [app/Livewire](C:/Users/djani/lendly/app/Livewire); [resources/views/components](C:/Users/djani/lendly/resources/views/components).

### NFR-12

**Growth without major redesign — Unverified**

Requirement in the document:

> The system architecture must support a growing number of users, item listings, and transactions without requiring major redesign.

Pagination exists for many lists, but the map loads every matching listing, dashboards aggregate collections in memory and chat-thread lists issue per-thread unread queries. No load/capacity evidence establishes growth compliance.

**Evidence:** [app/Livewire/Listings/Map.php](C:/Users/djani/lendly/app/Livewire/Listings/Map.php); [app/Livewire/Member/Dashboard.php](C:/Users/djani/lendly/app/Livewire/Member/Dashboard.php); [app/Livewire/Messages/Concerns/ListsThreads.php](C:/Users/djani/lendly/app/Livewire/Messages/Concerns/ListsThreads.php).

### NFR-13

**Automated backend frontend and manual validation — Partial**

Requirement in the document:

> The system must allow its backend functionality to be validated through automated request and response testing, and its user interface workflows through automated front-end testing, in addition to manual test cases.

A substantial PHPUnit/Livewire suite validates backend and rendered responses. No Selenium, Playwright or Dusk browser-workflow suite, Postman collection or completed manual execution log was found. Rendered response assertions do not exercise map JavaScript or actual browser interaction.

**Evidence:** [phpunit.xml](C:/Users/djani/lendly/phpunit.xml); [tests](C:/Users/djani/lendly/tests); [package.json](C:/Users/djani/lendly/package.json); repository test inventory.

### NFR-14

**Independently testable functions — Implemented**

Requirement in the document:

> The system must be implemented so that individual functions, such as registration form validation, item listing form validation, and booking status logic, can be validated independently.

Independent Geo unit tests and module-specific authentication, validation, lifecycle, cancellation, condition, history and notification tests exist.

**Evidence:** [tests/Unit/GeoTest.php](C:/Users/djani/lendly/tests/Unit/GeoTest.php); [tests/Feature/TermsAcceptanceTest.php](C:/Users/djani/lendly/tests/Feature/TermsAcceptanceTest.php); [tests/Feature/CancellationTest.php](C:/Users/djani/lendly/tests/Feature/CancellationTest.php); [tests/Feature/RentalLifecycleTest.php](C:/Users/djani/lendly/tests/Feature/RentalLifecycleTest.php).

### NFR-15

**Browser compatibility and device responsiveness — Unverified**

Requirement in the document:

> The system must function properly across major web browsers and be responsive on different devices.

Responsive Tailwind breakpoints are present, but no cross-browser/device execution evidence was available. Browser geolocation, map interaction and layout behavior were not tested in Chrome/Edge/mobile.

**Evidence:** [resources/views/layouts](C:/Users/djani/lendly/resources/views/layouts); [resources/views/livewire/listings/map.blade.php](C:/Users/djani/lendly/resources/views/livewire/listings/map.blade.php); [resources/css/app.css](C:/Users/djani/lendly/resources/css/app.css).

### NFR-16

**Email and map service interoperability — Partial**

Requirement in the document:

> The system must be able to interface with an email delivery service for account verification and password recovery, and with a map service for displaying the map view. Interfaces with other external services, such as live third-party payment gateways and courier services, are outside the current scope unless confirmed as part of the finalized requirements.

SMTP/service mail adapters and map tile integration exist. This environment uses a log mailer rather than delivering emails. External map tile availability and actual email inbox delivery were not verified.

**Evidence:** [config/mail.php](C:/Users/djani/lendly/config/mail.php); [resources/js/map.js](C:/Users/djani/lendly/resources/js/map.js); [resources/js/map.js](C:/Users/djani/lendly/resources/js/map.js); .env (mailer setting only).

### NFR-17

**Location only with user permission — Implemented**

Requirement in the document:

> The system must access a user’s device location only with the user’s permission.

Device location is requested through navigator.geolocation from explicit Use my location actions, subject to browser permission; no automatic location capture was found.

**Evidence:** [resources/js/map.js](C:/Users/djani/lendly/resources/js/map.js); [resources/js/map.js](C:/Users/djani/lendly/resources/js/map.js); [resources/js/map.js](C:/Users/djani/lendly/resources/js/map.js).

### NFR-18

**Acceptable map pin and filter response times — Unverified**

Requirement in the document:

> The map view must display listing pins, and update them when the renter changes the location, distance radius, keyword, or category, within an acceptable response time under normal load.

Reactive filters and a keyword debounce exist, but the map queries all matching listings and has no load/performance benchmark. Acceptable response time and normal load are undefined.

**Evidence:** [app/Livewire/Listings/Map.php](C:/Users/djani/lendly/app/Livewire/Listings/Map.php); [resources/views/livewire/listings/map.blade.php](C:/Users/djani/lendly/resources/views/livewire/listings/map.blade.php); [resources/js/map.js](C:/Users/djani/lendly/resources/js/map.js).

### NFR-19

**Accurate distances and nearest-first order — Partial**

Requirement in the document:

> Distances shown to renters and the nearest-first ordering of listings must be accurately based on the renter’s location and the item’s location.

Haversine distance computation has unit tests and valid nonzero coordinates drive ordering. The component treats zero-valued coordinates as missing, so valid locations do not always receive distances or sorting.

**Evidence:** [app/Support/Geo.php](C:/Users/djani/lendly/app/Support/Geo.php); [tests/Unit/GeoTest.php](C:/Users/djani/lendly/tests/Unit/GeoTest.php); [app/Livewire/Listings/Map.php](C:/Users/djani/lendly/app/Livewire/Listings/Map.php).

### NFR-20

**Browse and search without shared location — Implemented**

Requirement in the document:

> The system must still allow renters to search and browse items when their location is unavailable or has not been shared, without applying distance-based filtering or sorting.

Normal browse has no location dependency. Map discovery without a location uses a default center and does not apply distance filtering or nearest-first sorting; only coordinate-bearing listings can appear as map pins.

**Evidence:** [app/Livewire/Listings/Browse.php](C:/Users/djani/lendly/app/Livewire/Listings/Browse.php); [app/Livewire/Listings/Map.php](C:/Users/djani/lendly/app/Livewire/Listings/Map.php); [resources/js/map.js](C:/Users/djani/lendly/resources/js/map.js).

## Process flows status matrix

| ID | Requirement or flow | Status |
| --- | --- | --- |
| [B.1](#b.1) | Registration and login | **Partial** |
| [B.2](#b.2) | Item listing and management | **Partial** |
| [B.3](#b.3) | Item search and map browsing | **Partial** |
| [B.4](#b.4) | Request approval and booking | **Partial** |
| [B.5](#b.5) | Payment and transaction | **Partial** |
| [B.6](#b.6) | Pickup or delivery | **Partial** |
| [B.7](#b.7) | Tracking and return | **Partial** |
| [B.8](#b.8) | Ratings and reviews | **Partial** |
| [B.9](#b.9) | Admin and dispute resolution | **Partial** |
| [B.10](#b.10) | Rental cancellation | **Partial** |

## Process flows findings

### B.1

**Registration and login — Partial**

Related requirements: FR-01–04, FR-39.

Registration, uniqueness validation, verification, reset and login exist. Current mail is logged; registration automatically signs in, verification redirects to the dashboard instead of the login page, and per-login Owner/Renter selection is absent.

**Evidence:** [resources/views/livewire/pages/auth/register.blade.php](C:/Users/djani/lendly/resources/views/livewire/pages/auth/register.blade.php); [app/Http/Controllers/Auth/VerifyEmailController.php](C:/Users/djani/lendly/app/Http/Controllers/Auth/VerifyEmailController.php).

### B.2

**Item listing and management — Partial**

Related requirements: FR-05–08, FR-47.

Listing creation, location, photos and edit/pause/removal exist. Available-date selection is not modeled, photos/coordinates are optional, and submissions/edits wait for admin approval instead of publishing after validation. Removal breaks history relations.

**Evidence:** [app/Livewire/Owner/Listings/Form.php](C:/Users/djani/lendly/app/Livewire/Owner/Listings/Form.php); [app/Livewire/Owner/Listings/Form.php](C:/Users/djani/lendly/app/Livewire/Owner/Listings/Form.php); [app/Models/Listing.php](C:/Users/djani/lendly/app/Models/Listing.php).

### B.3

**Item search and map browsing — Partial**

Related requirements: FR-09–10, FR-48–53.

Search, filters, details/calendar, pins, permission-based positioning and radii exist. Pin previews omit photos; valid zero coordinates disable distances/radius/order. List browsing has no location filter and live map behavior was not browser-tested.

**Evidence:** [app/Livewire/Listings/Browse.php](C:/Users/djani/lendly/app/Livewire/Listings/Browse.php); [app/Livewire/Listings/Map.php](C:/Users/djani/lendly/app/Livewire/Listings/Map.php); [resources/js/map.js](C:/Users/djani/lendly/resources/js/map.js).

### B.4

**Request approval and booking — Partial**

Related requirements: FR-11–16, FR-40.

Request notification, approval/decline and booking generation exist. Renter accepts before submitting and owner during approval, rather than both after approval. Approval does not enforce recorded renter acceptance and only the renter gets booking confirmation.

**Evidence:** [app/Livewire/RentalRequests/Create.php](C:/Users/djani/lendly/app/Livewire/RentalRequests/Create.php); [app/Livewire/Owner/RentalRequests/Index.php](C:/Users/djani/lendly/app/Livewire/Owner/RentalRequests/Index.php).

### B.5

**Payment and transaction — Partial**

Related requirements: FR-16–17, FR-41–42.

Cost computation and clearly labelled simulated payment produce records and a renter receipt. Owner receives an earnings breakdown instead of a receipt. Dates block on approval; item never becomes Reserved after payment. Live payment gateways are outside scope.

**Evidence:** [app/Livewire/Renter/Rentals/Show.php](C:/Users/djani/lendly/app/Livewire/Renter/Rentals/Show.php); [app/Models/Listing.php](C:/Users/djani/lendly/app/Models/Listing.php); [resources/views/livewire/owner/rentals/show.blade.php](C:/Users/djani/lendly/resources/views/livewire/owner/rentals/show.blade.php).

### B.6

**Pickup or delivery — Partial**

Related requirements: FR-18, FR-43–44.

Fulfillment selection, request chat and independent pickup acknowledgements exist; both acknowledgements activate the rental. No structured pickup/delivery time/place confirmation or automatic confirmed-schedule notification to both parties was found.

**Evidence:** [app/Services/RentalLifecycle.php](C:/Users/djani/lendly/app/Services/RentalLifecycle.php); [app/Models/Rental.php](C:/Users/djani/lendly/app/Models/Rental.php); [app/Livewire/Messages/Show.php](C:/Users/djani/lendly/app/Livewire/Messages/Show.php).

### B.7

**Tracking and return — Partial**

Related requirements: FR-19–22, FR-24.

Status display, reminder/overdue commands, return confirmation, condition records and completed history exist. Live refresh and scheduler operation are unverified. Owner inspection/closure additionally waits for renter return confirmation; deleted listings can break history.

**Evidence:** [app/Services/RentalLifecycle.php](C:/Users/djani/lendly/app/Services/RentalLifecycle.php); [app/Policies/RentalPolicy.php](C:/Users/djani/lendly/app/Policies/RentalPolicy.php); [routes/console.php](C:/Users/djani/lendly/routes/console.php).

### B.8

**Ratings and reviews — Partial**

Related requirements: FR-23, FR-45.

Both parties have post-completion review forms. Public user profiles publishing all received reviews are absent; only the renter gets a completion review notification. Renter review actions allow owner impersonation through the renter component.

**Evidence:** [app/Livewire/Renter/Rentals/Show.php](C:/Users/djani/lendly/app/Livewire/Renter/Rentals/Show.php); [app/Services/RentalLifecycle.php](C:/Users/djani/lendly/app/Services/RentalLifecycle.php); [routes/web.php](C:/Users/djani/lendly/routes/web.php).

### B.9

**Admin and dispute resolution — Partial**

Related requirements: FR-26–30, FR-46.

User suspension, listing moderation, transaction overview, transaction disputes and resolution notifications exist. Admin cannot manage rental transactions directly; standalone listing/user reporting, general evidence attachments and a complete transaction investigation screen are absent.

**Evidence:** [app/Livewire/Admin/Rentals/Index.php](C:/Users/djani/lendly/app/Livewire/Admin/Rentals/Index.php); [app/Livewire/Admin/Disputes/Index.php](C:/Users/djani/lendly/app/Livewire/Admin/Disputes/Index.php); [resources/views/livewire/admin/disputes/index.blade.php](C:/Users/djani/lendly/resources/views/livewire/admin/disputes/index.blade.php).

### B.10

**Rental cancellation — Partial**

Related requirements: FR-34–37.

Booking-detail cancellation computes fees, notifies the owner, records metadata and releases dates. Approved-request cancellation instead changes only request status, leaving a payable booking, no fee and no owner notice. Cancellation remains possible after owner handover until renter receipt.

**Evidence:** [app/Livewire/Renter/RentalRequests/Index.php](C:/Users/djani/lendly/app/Livewire/Renter/RentalRequests/Index.php); [app/Models/Rental.php](C:/Users/djani/lendly/app/Models/Rental.php); [app/Services/RentalLifecycle.php](C:/Users/djani/lendly/app/Services/RentalLifecycle.php).

## Focused gap checks

| Probe | Observed result |
| --- | --- |
| Approved request cancellation | Request cancelled; booking still payment-pending; no fee/owner notice; dates freed; booking subsequently paid successfully. |
| Approval without renter acceptance | Owner acceptance created a rental even though renter acceptance was null; owner had zero confirmation notices. |
| Owner invoking renter review | Owner submitted a renter-to-owner review through the renter component. |
| Valid zero-coordinate location | A latitude of zero produced no distance even with a radius selected. |
| Owner-only handover | Owner pickup timestamp was recorded, but rental remained cancellable. |
| Listing deletion and history | Rental/request records remained, but their normal listing relationships returned null. |
| Availability changed after opening request form | A new request was accepted after the listing was marked unavailable. |

Additional risks such as simultaneous approval/payment, partial database writes, cancelled-earnings reconciliation and JavaScript popup safety were inspected in code rather than exhaustively exercised. Browser map interactions, permission denial, current service connectivity and operational scheduling require acceptance testing.

## Process details requiring baseline clarification

- B.2 says listings publish after validation; the implementation requires admin approval and reapproval after edits. Decide whether moderation is an intended additional step.
- B.2 mentions available dates, while FR-05 only states availability generally. The system offers a boolean availability flag and blocks booked dates; it has no owner-defined availability schedule.
- B.4 places terms acceptance after approval. The implementation asks the renter before request submission and the owner during approval. Both-party agreement is the critical invariant; decide whether the exact step order is mandatory.
- FR-44 says ongoing after handover, while B.6 includes both Handed Over and Received. The implementation waits for both acknowledgements and uses Active Rental wording. Specify whether owner handover alone should activate a rental.
- B.7 describes owner inspection/closure; implementation additionally requires renter return confirmation first. Decide whether that extra dependency is acceptable.
- NFR-05 and NFR-18 use acceptable response time and normal load without numeric targets; NFR-09 leaves minimal steps undefined; NFR-08 names no concrete standard; NFR-12 provides no growth volume. Set measurable acceptance criteria before final sign-off.

The embedded overview agrees with the main assessed workflow, including both-party terms, Reserved after payment, review publication, cancellation and admin supporting flows. The navigation map has the same selected-role and admin-management gaps identified above. Initial UI images are design references; screenshots alone were not treated as working-feature evidence.
