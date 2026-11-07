# Demo database seeding

Updated 7 November 2026. By default, demo seeders run only when `APP_ENV` is `local` or `testing`. A command-scoped override is available for an intentional deployed demo (see below). They add synthetic accounts, listings and examples; they do not import another installation's database or uploaded photos.

## Explicit hosted demonstration dataset

The ordinary seeders remain disabled in production unless the separate process-scoped override below is used. For the requested 100-member, 250-listing public demo, prefer this dedicated command with random credentials and bundled placeholder images. Deploy it and run its preview inside the Lendly application container:

```sh
php artisan demo:seed-hosted
```

After reviewing the environment and dataset, apply it explicitly:

```sh
php artisan demo:seed-hosted --apply --confirm-production
```

This separate command creates up to 100 synthetic members (`hosted.demo1@tala.test` through `hosted.demo100@tala.test`) and 250 clearly labelled listings, with location pins at their named city centres. It creates no administrators, bookings, payments or financial history. New synthetic members receive demonstration verification so their items can be used in the rental demo. Each gets a random password that is neither displayed nor the local seeders' shared `password`; these are fixture owners, not public shared-login accounts. The `hosted-marketplace-demo-v1` reference identifies members belonging to this dataset.

The preview does not write records. Applying requires both flags, wraps all database writes in a transaction, and preserves matching existing records. An existing target email without this fixture's reference aborts the operation and rolls back the changes. Existing inactive or edited members are not reactivated to fill missing listings. The normal `db:seed --class=HostedMarketplaceSeeder` invocation is refused; only the explicit command enables this dataset.

Hosted placeholder images are bundled with the app at `public/images/hosted-demo-placeholder.svg`. They are served independently of an ephemeral upload directory and remain available after redeployment. Regular user-upload URLs retain their existing behavior. No migrations or changes to `APP_ENV` are needed for the hosted command. Run it inside the deployed application container so it uses that installation's database; a local test run does not seed Railway.

## Normal local setup

Configure an existing empty MySQL database in `.env`, then run these commands from the project directory:

```powershell
php artisan migrate
php artisan db:seed
php artisan storage:link
```

The default seeder creates the demo admin, owner and renter, the category catalogue, additional demo members and illustrated listings, and the marketplace demo below. On an entirely fresh database it also creates the legacy example rental/payment/history/message/dispute dataset. On an existing installation it skips that historical dataset so seeding does not attach synthetic financial records or damage reports to existing activity. The default database writes are wrapped in a transaction.

Newly created demo accounts use password `password`. Existing passwords, names, roles, suspensions, verification state and soft-deleted records are preserved. Fresh owner/renter fixtures are marked identity verified for demonstrations; this does not represent review of a real person's ID. Outside local/testing environments, creating these fixtures requires the explicit override below. Existing unverified or suspended accounts are not upgraded to make seeding work.

Main accounts:

| Email | Purpose |
| --- | --- |
| admin@tala.test | Administrator |
| owner@tala.test | Demo member, owner interface |
| renter@tala.test | Demo member, renter interface |
| test@lendly.test | Additional login account |

Both member accounts can use the owner/renter interface switch. Seeded `.test` addresses are local fixtures rather than addresses accepted by public Gmail-only registration.

## Smaller marketplace dataset

```powershell
php artisan db:seed --class=MarketplaceDemoSeeder
```

This standalone command creates the category catalogue, two verified demo members (`marketplace.owner@lendly.test` and `marketplace.renter@lendly.test`), three listings and one pending three-day camera request. The projector is pending moderation; the camera and drill are published. Each new item has dates, opening hours, a map pin and a local placeholder photo. The camera request records exact Philippine pickup/return times and uses the application's normal quotation/submission service.

Repeat runs preserve matching fixtures and their subsequent status, price and password changes. Cancelling the example request does not cause seeding to replace it. If an existing marketplace account is unverified, suspended, deleted or ineligible, its state is retained and new marketplace fixtures are skipped. Existing listing availability dates are not extended on every seed run; expired demo listings can be adjusted through the normal owner form.

## Explicit deployed demo (Railway)

After deploying this code, keep `APP_ENV=production`. In the **lendly app service → Console** (not the MySQL service), run:

```bash
cd /app
ALLOW_DEMO_SEEDING=1 php artisan db:seed --class=MarketplaceDemoSeeder
```

Answer yes to Laravel's production confirmation. `--force` only skips that prompt; it does not replace the override. The prefix enables demo seeding only for this command, including when Laravel configuration is cached. Use the command prefix instead of a permanent Railway environment variable.

The override is shared by all demo seeders, so use the named marketplace command for this smaller dataset. Without the exact process environment value `ALLOW_DEMO_SEEDING=1`, production demo seeding remains blocked.

This creates two identity-verified demo accounts with the known password `password`, three listings, categories and a pending request. Change the demo passwords before opening the site to public users. Existing matching accounts and listings are preserved, and rerunning does not duplicate unchanged fixtures. This command writes to the deployed database and public upload storage; it is not a dry run.

## Optional volume datasets

```powershell
php artisan db:seed --class=BulkDemoSeeder
php artisan db:seed --class=DemoAccountVolumeSeeder
```

BulkDemoSeeder creates up to 100 dedicated demo members and 250 numbered listings with dates and placeholder images. Rerunning it skips matching existing and soft-deleted items and preserves account changes. A suspended or unverified existing bulk owner is not reactivated to add its items.

DemoAccountVolumeSeeder tops up the named demo owner's and renter's list/history modules. Run it after the normal seed. It requires the two named accounts to be eligible and keeps existing counts; it also handles a setup with no additional members.

TransactionSeeder is a legacy fresh-history fixture and skips a database that already has requests or rentals. DemoContentSeeder skips existing damage/dispute/message/notification data. They are not repair or synchronization tools for partially populated transaction history.

## Category conflicts and repeat runs

CategorySeeder can run independently and does not reset existing category names. A seeded slug already assigned to another parent is reported as a conflict rather than silently moving that category and breaking existing listings. ListingSeeder resolves subcategories within the correct parent.

Account fixtures match their email; named listing fixtures match owner and name. Renaming a named fixture makes it a different record for seeding purposes. The numbered bulk fixtures also preserve their original sequence when an item is soft-deleted. No seeder deletes or truncates existing records.

The tests use isolated SQLite and fake public storage. Verification does not require reseeding the working MySQL database. Do not use `migrate:fresh` to apply these fixes to an existing installation; it drops tables.
