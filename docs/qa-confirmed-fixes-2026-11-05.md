# Confirmed QA fixes

Implemented locally on 5 November 2026 against main commit 015d479, following review of the Requirements v2 Selenium report. This change addresses D-09, D-06, D-03, D-04 and D-08. It does not claim to resolve the separate Google OAuth observation or the proposed Gmail, map consistency and selfie UX changes.

| Finding | Result |
| --- | --- |
| D-09 Late-night default request period | Defaults compare full Philippine date/time instants before formatting. When one hour no longer fits before closing, the form chooses the next opening within the listing's dates. This avoids 23:00 to 00:00 on the same date and avoids charging an extra daily date merely to cross midnight. A final available daily window can end at closing rather than advancing beyond the listing's dates. Saved waitlist details and server-side period validation remain in place. |
| D-06 Receipt timezone | Owner, renter and administrator paid timestamps display Asia/Manila time with an explicit Philippine time label. A copy is converted for display; stored UTC timestamps are retained. Tests cover conversion into the following calendar date. |
| D-03 Map-pin error | The validation summary deduplicates identical messages. The latitude/longitude errors also share one deduplicated inline map message. Both coordinates remain validated separately. The same guidance appears once in the summary and once beside the map, following the form's existing summary-plus-field convention. |
| D-04 Removal reason | Removing a published listing opens a reason modal. A trimmed reason of at most 255 characters is required before confirmation. Removal stores the reason in the existing moderation reason field, marks the listing Inactive and notifies the owner within one transaction. The owner sees the reason in My Listings, Edit Listing and the notification; administrators also see it in their list. Cancel leaves the listing unchanged. Confirmation reloads and locks the listing and rejects stale actions. No schema migration is needed. |
| D-08 Face runtime setup | Documentation specifies Python 3.10 through 3.13, recommends 3.13 and supplies explicit Windows and Unix interpreter commands. It explains that Python 3.14 is unsupported by the current NumPy pin. Dependency versions are unchanged. |

## Verification

- New focused regression tests: 12 passed, 77 assertions. Cases include 21:30, 22:00, 22:30, 22:59 and 23:30 PHT; opening and closing windows; hourly pricing; a final available daily date; all three receipts across midnight; deduplicated pin errors; removal reason validation, cancellation, stale state and owner visibility.
- JavaScript suite: 55 passed.
- Production Vite build passed. Changed PHP files formatted with Pint. Whitespace checks passed.
- Full backend run: 1,100 scenarios executed, 8,479 assertions, one failure in an existing reservation display assertion. The pulled UI emits an en dash as `&ndash;`, while the old test compared raw markup with the literal character. The assertion now compares decoded visible text, retaining the exact agreed dates. All 9 reservation tests then passed, 65 assertions. Combining the full run and corrected affected-suite retest verifies all 1,100 scenarios with no remaining known failed test. Raw logs: storage/logs/qa-fixes-full.txt, qa-fixes-full.xml and qa-fixes-reservation-retest.txt.
- The Selenium IT-27 scenario now enters the mandatory removal reason and checks owner visibility. Syntax check passed; the full Selenium suite was not rerun.

The initial manual administrator login was rejected by automatic approval review. After the user explicitly authorized the existing demo administrator and owner accounts, the administrator login succeeded. The live removal reason modal opened and Cancel dismissed it; the existing booking #25 receipt displayed `Aug 26, 2026 12:07 AM (Philippine time)`. Screenshots are in qa-fix-evidence-2026-11-05. No existing local listing was removed and no live payment was confirmed during verification.

## Before and after

| Area | Before | After |
| --- | --- | --- |
| Request opened at 10:30 PM PHT with an all-day window | 11:00 PM to 12:00 AM on the same date; validation error | Next day's opening, 12:00 AM to 1:00 AM; valid one-date default |
| UTC payment recorded at 19:25 on 3 November | Receipt shows 3 November, 7:25 PM | Receipt shows 4 November, 3:25 AM Philippine time |
| Missing map coordinates | Identical required message twice in summary and twice at map | Once in summary and once at map |
| Administrator removes a published listing | Browser confirmation with no reason field | In-app reason modal; reason saved and visible to owner |
| Face runtime setup | Python 3.10+ with unqualified interpreter selection | Python 3.10 through 3.13; explicit 3.13 environment commands |

The before column records verified source behavior and the supplied QA report; it is not a reconstructed screenshot. The after images are actual current application captures.
