# Verification — 2026-09-14

## Environment

- Windows, PHP 8.4.22, PDO MySQL.
- MariaDB 10.4.32 (XAMPP); MySQL-compatible schema/queries. A separate MySQL 8 server was not available for execution.
- Microsoft Edge headless / Playwright, desktop 1440×1100 and mobile 390×844.
- Demo server: `127.0.0.1:8765`; integration test used independent `_test` databases.

## Results

- **253 HTTP/database assertions passed** (`tests/workflows.php`). The count includes checks for PHP errors in each response.
- Admin flow passed: login → create four-field player → separately create account → create match → attendance → complete score → review/correct stats.
- Player flow passed: login → next match → YES/NO changes → completed match → own goals/assists → updated totals.
- Public pages passed: overview, history, match detail, squad, profile, sorted statistics.
- Security/validation passed: CSRF rejection, anonymous/player admin access blocked, forged player ID cannot change another player's stats, private contact details hidden, stored HTML escaped, negative/fractional stats rejected, duplicate username rejected, inactive/locked sessions lose access, password reset/change.
- Match rules passed: earliest future Upcoming, after-kickoff rejection, nonparticipant rejection, Completed requires both scores, away display order, computed W/D/L, canceled matches excluded, mismatch warning and correction, cascade deletion, no-match empty states.
- Player model tests passed: jersey strings Y-07, 2003, 007, A-10, GK-01, whitespace and 20-character values round-trip unchanged and render on five pages. Empty/overlong jerseys and invalid birth years rejected.
- Inactive tests passed: omitted checkbox saves false; future attendance hidden/blocked even for Admin; historical appearances/goals/assists remain unchanged.
- Existing demo migration verified all 24 IDs and jerseys, extracted birth years, and byte-equivalent accounts/attendance/stat rows. Original player rows backed up privately under var/.
- Browser flow passed: login, RSVP, profile navigation, mobile hamburger, player search, admin login, 11 mobile page overflow checks; exactly four editable player fields and text jersey input; no JavaScript page errors.
- Desktop/mobile screenshots visually inspected; saved in `var/screenshots/desktop.png`, `mobile.png`, `admin.png`, `player-form-desktop.png`, `player-form-mobile.png`.
- All application PHP files passed `php -l`.

## Fixed during verification

MySQL/MariaDB subtracting unsigned score columns raised an out-of-range error for losses. Goal difference now casts both operands to SIGNED before subtracting. Re-ran the integration workflows successfully after the fix.

## Scope

Branding update: official JPG installed unchanged; navy/coral/white theme reviewed in desktop and mobile screenshots. Existing browser QA passed again, including login, RSVP, menu/search, four-field player form and 11 mobile overflow checks. PHP lint passed for all four modified templates.

No hosting account/domain was supplied, so cPanel/GoDaddy deployment is documented in README, not performed. Browser QA is local; no email/third-party service is required. Multi-season filtering and the other future extensions from the brief are intentionally outside V1.
