# Eastern Standard Studio

A small-business WordPress site with consultation booking, a contact form, a
project portfolio and services — built on WordPress 7.1 as a block theme with a
custom booking engine and a self-hosted automation workflow.

**Case study:** https://andipyk.github.io/booking-contact-general/

Eastern Standard Studio is invented. This was self-initiated to demonstrate a
way of working; it is not client work and was not commissioned.

---

## 1. Business problem

A design practice loses work between the enquiry and the first conversation.
Someone reads about a project, wants twenty minutes to find out whether their
own job is even feasible, and hits a contact form that disappears into an inbox.
Two days later a partner replies suggesting three times. One of them is already
gone. The thread runs four messages before anything is in a diary, and a share
of prospects give up somewhere in the middle.

The practice also cannot show its work properly. Each project has facts a
prospective client wants — where it was, what it cost in time, how big,
which disciplines were involved — and those facts end up retyped into prose, so
they are inconsistent between pages and impossible to filter.

## 2. Business goal

Someone who lands on a project page can be holding a confirmed appointment
ninety seconds later, without anybody at the practice touching it. Specifically:

- A visitor picks a slot and gets a confirmation, in one visit, with no email
  exchange.
- Two people clicking the same slot at the same moment produce exactly one
  booking. Never two.
- Every project's facts are entered once and render identically everywhere they
  appear.
- The site is fast enough and structured enough that the work is findable.

## 3. Strategy

**Build the booking engine rather than install one.** Booking plugins solve this
in general, which means carrying a settings screen for every case the studio
does not have. The requirement here is narrow — one appointment type, one
calendar, a fixed weekly pattern — and the interesting part is the correctness
question a plugin would hide. That question is worth answering in the open.

**Treat double booking as a database problem.** The obvious implementation reads
"is this slot free?" and then writes. Two simultaneous requests both pass the
read. The window is milliseconds and it is real, and a double-booked principal
is precisely the failure a booking system exists to prevent. Correctness belongs
somewhere it can be enforced rather than in PHP timing.

**Separate the data layer from the theme.** Everything functional lives in a
plugin; the theme only renders. A practice that redesigns in three years should
not lose its bookings to a theme switch.

**Use the platform instead of working around it.** WordPress 7.1 ships a Block
Bindings API and an Interactivity API. Project facts bind directly to core
blocks, and the booking calendar is server-rendered with directives rather than
a client-side app. That decision is why the pages score what they score.

**Self-host the automation.** A cloud automation platform cannot reach a local
site without a tunnel whose URL changes on every restart. Running n8n as a
service on the same Docker network gives a hostname that never moves, and the
workflow is a JSON file in this repository rather than a diagram of one.

## 4. Technical solution

| Layer | Choice | What it solves |
|---|---|---|
| Runtime | WordPress 7.1, PHP 8.4, MariaDB 11 | current core; `theme.json` v3 and Block Bindings available |
| Theme | Block theme, no page builder | 100/100 performance; no builder payload |
| Data | 4 CPTs, registered meta, 2 taxonomies | project facts entered once, rendered anywhere |
| Display | Block Bindings (`core/post-meta` + a custom source) | no shortcodes, no field-plugin template tags |
| Booking UI | Interactivity API, `apiVersion: 3`, server-rendered | slots in the HTML before JS; CLS 0 |
| Correctness | Custom table with `UNIQUE KEY slot_start` | double booking impossible, not merely unlikely |
| Automation | n8n on the internal Docker network | confirmation emails and callback; no tunnel |
| Cache | Redis + phpredis 6.3.0 in the one image web and CLI share | CLI and web behave identically |
| Mail | Mailpit | every message inspectable, nothing leaves the stack |
| SEO | Rank Math, configured from code | reproducible; schema per post type |

### Data flow

```
Browser                WordPress (ess-core)              n8n                Mailpit
   │                          │                            │                   │
   ├─ GET /availability ─────>│ Availability::slots()      │                   │
   │<── slots in studio TZ ───┤   generated local → UTC    │                   │
   │                          │                            │                   │
   ├─ POST /booking ─────────>│ 1 nonce + honeypot +       │                   │
   │   (nonce, signed stamp)  │   time trap + rate limit   │                   │
   │                          │ 2 Availability::resolve()  │                   │
   │                          │ 3 wp_insert_post()         │                   │
   │                          │ 4 Slot_Lock::acquire()     │                   │
   │                          │    └─ duplicate key → 409  │                   │
   │<── 201 + reference ──────┤ 5 POST to webhook ────────>│                   │
   │                          │                            ├─ client email ───>│
   │                          │                            ├─ studio email ───>│
   │                          │  POST /booking/status      │                   │
   │                          │<── x-ess-signature ────────┤                   │
   │                          │  hash_equals() → confirmed │                   │
```

If the automation never calls back, the booking stays `pending` and an hourly
cron releases the hold after 30 minutes, so a failed workflow costs one slot for
half an hour instead of permanently.

### The slot lock

`wp-content/plugins/ess-core/includes/class-slot-lock.php`

```php
CREATE TABLE {$table} (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    slot_start datetime NOT NULL,
    slot_end datetime NOT NULL,
    booking_id bigint(20) unsigned NOT NULL,
    created_at datetime NOT NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY slot_start (slot_start),
    KEY booking_id (booking_id)
) {$collate};
```

```php
// No SELECT first, on purpose: the INSERT is the test, and it is atomic.
$inserted = $wpdb->insert( self::table(), [ /* … */ ], [ '%s', '%s', '%d', '%s' ] );

if ( false === $inserted ) {
    return false;   // duplicate key — the REST layer turns this into a 409
}
```

### Two binding sources, chosen by what the value is

`wp-content/themes/eastern-standard/templates/single-ess_project.html`

Text that is already display-ready goes through core:

```html
<!-- wp:paragraph {"metadata":{"bindings":{"content":{
      "source":"core/post-meta","args":{"key":"ess_location"}}}}} -->
<p>—</p>
<!-- /wp:paragraph -->
```

A square footage is stored as an integer so it stays sortable, which means it
needs formatting at render time. `core/post-meta` has no formatting layer, so
the plugin registers its own source and `4800` renders as `4,800 sq ft`:

```php
register_block_bindings_source( 'ess/project-spec', [
    'label'              => __( 'Project spec', 'ess-core' ),
    'get_value_callback' => [ self::class, 'get_value' ],
    'uses_context'       => [ 'postId', 'postType' ],
] );
```

### Timezones

Slots are generated against the studio's wall clock and stored as UTC. On a
spring-forward day PHP silently rolls a non-existent local time forward, so the
builder compares the formatted result against what was asked for and rejects a
time no clock ever shows:

```php
$built = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $date . ' ' . $time, $tz );
if ( ! $built || $built->format( 'Y-m-d H:i' ) !== $date . ' ' . $time ) {
    return null;
}
```

## 5. Business result

Every figure below came from a command in `bin/`, and every one of those
commands is in this repository.

**One booking survives twelve simultaneous attempts.**

```
$ ./bin/concurrency-test.sh 12
    contested slot: 2026-09-17 17:30:00 (Thu 17 Sep, 1:30 pm)
    201 Created  : 1
    409 Conflict : 11
    lock rows    : 1
    booking posts: 1
PASS — one booking created, 11 rejected, one lock row, no orphans.
```

**A booking made in a browser is confirmed without anyone touching it.**
Reference `ESS-VKR0XB`, booked at 02:50:07 UTC:

```
ess_reference   : ESS-VKR0XB
ess_slot_start  : 2026-09-21 15:00:00      (11:00 am EDT, stored UTC)
ess_status      : confirmed
ess_confirmed_at: 2026-09-16 02:50:08
ess_sync_note   : confirmation email sent
```

n8n run 8 took 1,062 ms end to end. Both emails are in Mailpit.

**Lighthouse, six pages, desktop preset:**

| Page | Perf | A11y | Best practices | SEO | LCP | CLS |
|---|---|---|---|---|---|---|
| Home | 100 | 100 | 100 | 100 | 0.5 s | 0 |
| Projects | 100 | 100 | 100 | 100 | 0.5 s | 0 |
| Single project | 100 | 100 | 100 | 100 | 0.7 s | 0 |
| Services | 100 | 100 | 100 | 100 | 0.4 s | 0 |
| Booking | 100 | 100 | 100 | 100 | 0.5 s | 0 |
| Contact | 100 | 100 | 100 | 100 | 0.4 s | 0 |

Raw reports are in `docs/lighthouse/`. The first run scored 92 on SEO for two
pages and 95–98 on accessibility for four; those were fixed at the cause — the
heading outline, a contrast failure and two missing meta descriptions — rather
than waived. The case study page lists what each one was.

**Other checks:** DST assertions pass across the 1 November 2026 transition;
zero horizontal overflow at 390px on all seven public pages; the callback
rejects a missing or wrong signature with 403 and an unknown booking with 404;
and after exercising every page and both write routes, `debug.log` holds one
informational line and no PHP warnings, notices or fatals.

---

## Running it

```bash
cp .env.example .env     # then fill in the values
./bin/setup.sh
```

| Service | URL |
|---|---|
| Site | http://localhost:8100 |
| phpMyAdmin | http://localhost:8101 (starts with `docker compose --profile tools up -d`) |
| Mailpit | http://localhost:8102 |
| n8n | http://localhost:8103 |

`setup.sh` is idempotent — re-run it after editing the plugin list or
interrupting a run. WP-CLI runs through `bin/wp` (for example
`bin/wp plugin list`), in a throwaway container on the same image, user and
environment as the web server.

### Scripts

| Script | Does |
|---|---|
| `bin/setup.sh` | provisions everything from an empty volume |
| `bin/seed-content.php` | projects, services and pages as code |
| `bin/configure-seo.php` | Rank Math settings and per-post schema |
| `bin/import-n8n.sh` | imports and publishes the workflow |
| `bin/concurrency-test.sh` | fires N simultaneous bookings at one slot |
| `bin/dst-test.php` | asserts slot generation across a DST boundary |
| `bin/lighthouse.sh` | audits six pages, writes `docs/lighthouse/` |
| `bin/n8n-executions.sh` | dumps execution history from n8n's database |
| `bin/gen-images.py` | regenerates the project images |

### Layout

```
compose.yaml       the stack; WP-CLI and phpMyAdmin run on demand (profiles)
docker/wordpress/  the WordPress image web and CLI share: phpredis + WP-CLI
bin/               provisioning, seeding and verification scripts
n8n/               workflow and credential JSON, imported at setup
wp-content/
  mu-plugins/      environment glue only (SMTP, timezone)
  plugins/ess-core/   all functionality
  themes/eastern-standard/   presentation only
docs/              Lighthouse reports, execution history, screenshots
portfolio/         the case-study page (static HTML, published to GitHub Pages)
.github/workflows/ pages.yml deploys portfolio/ on every push to main
```

WordPress core lives in a named Docker volume and is not in this repository.
