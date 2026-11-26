# SN Tasks

مساحة عمل يومية شخصية (عربي / English) لتنظيم المهام والخطط والأهداف وجلسات التركيز، مع لوحة إدارة للمنصة.
A bilingual (Arabic RTL / English) personal productivity workspace with an admin console.

## What it does

| Area | Details |
| --- | --- |
| Sign in / sign up | Email + password, remember me, password strength meter, password reset by email |
| Overview | Greeting, daily score, today's tasks, quick links, weekly goal progress |
| Command center | Today's top priorities and timeline, focus timer (10/25/30/60 min), daily review, life areas (members add their own), a calendar whose days hold a picture and a note, 7-day rhythm, "universe" map, rule-based assistant |
| Plans | Daily, weekly, monthly and yearly task lists with period navigation, filters, priorities, categories, due dates, undoable delete |
| Goals | Measurable goals per period with progress steps |
| Habit tracker | Monthly grid of habits by week with daily totals, per-habit goals and analysis, a consistency chart, daily mood and motivation ratings with weekly averages, and monthly notes |
| Reminders | A reminder time on any task; due reminders appear in the notification bell with a toast and optional chime, and as browser notifications on HTTPS |
| Achievements | Extra badges defined by administrators (metric + threshold), plus badges awarded automatically (first task, 10/50/100 tasks, 7/30-day streaks, first goal, 10 focus hours) |
| Growth & motivation | Library of texts published by administrators |
| Education | Lessons (title, text, image) published by administrators, shown as cards that open a lesson page; lessons can be subscriber-only and administrators grant premium access per member |
| Messages | A private thread between each member and the administrators, with unread badges |
| Profile | Photo and names, settings (language: Arabic, English or French; time zone; goals; per-account notification switches), security (password change, list of signed-in devices, sign out of other devices) and account statistics |
| Notifications | Task reminders, a morning motivation line, a welcome back after a day or more away, and bedtime / wake-up reminders; each can be switched off. They appear while the app is open |
| Points and achievements | Administrators define achievements with one or more conditions, a period (once, daily, weekly, monthly, yearly) and the points they award; points are added automatically when every condition holds. The profile shows the balance, the total earned and a 30-day chart |
| Store | Items priced in points and managed by administrators: titles, avatar frames, colour themes for the whole app, profile photos, and products in admin-made sections (e.g. books) that hand a private file or a link to buyers |
| Tip of the day | A free daily draw on the home page: lines roll past and stop on one; one draw per member per day. Administrators add lines in bulk, edit, hide and translate them |
| Paid subscriptions | Paid outside the site and switched on by hand, optionally until a date. Each subscription opens only the services the administrator picks for that member: subscriber-only lessons, books, and store items reserved to subscribers. An editable note above every private conversation explains how to subscribe. Everything else stays free |
| Books | A paid library: covers and descriptions are visible to all; the PDF/EPUB is served only to members it was opened for |
| Translations | Text written by administrators (motivation, lessons, achievements, store, skill paths, broadcasts) can be given per language; members read the version for their language and fall back to the original. A translation is retired when its original is reworded |
| Honour board | Today's top finishers, the most committed members of the week, and the most points overall and this month |
| Broadcasts | Administrators send a notification to every member on the spot, or schedule one message per day for the week |
| Growth journey | Performance DNA (best day and time, on-time rate, 30-day comparison, rule-based tips), a growth timeline of automatic milestones with personal notes, goal roadmaps (stages, steps, next step), vision boards (images, quotes, dreams linked to goals), admin-written skill paths with levels, quizzes and challenges, and a portfolio shareable by link with privacy settings |
| Backup | Members download all their data as JSON; `deploy/backup.sh` backs the database and uploads up nightly and reports its status in the app |
| Admin center | Platform stats, user search, session revocation, editable home-page copy, motivation studio, lesson manager, member inbox, audit log. A full administrator can appoint moderators and choose which areas each one manages; the server enforces it per area |

## Stack

- **Back end:** PHP 8.3, Laravel 13, Eloquent, Laravel Sanctum (cookie-based SPA auth), MySQL in production, SQLite for local development and tests.
- **Front end:** Vue 3 (`<script setup>`, TypeScript strict), Pinia, Vue Router, Chart.js, Vite. Plain CSS driven by design tokens.

## Run it locally

Requirements: PHP 8.3+, Composer, Node 20+.

```bash
composer install
```

```bash
cp .env.example .env
```

For local work, switch the database to SQLite: in `.env` set `DB_CONNECTION=sqlite` and delete the `DB_HOST` … `DB_PASSWORD` lines. Set `ADMIN_NAME`, `ADMIN_EMAIL` and `ADMIN_PASSWORD` to create the first administrator.

```bash
php artisan key:generate
```

```bash
touch database/database.sqlite && php artisan migrate --seed
```

```bash
npm install
```

Then run the two dev servers in separate terminals and open http://localhost:8000:

```bash
php artisan serve
```

```bash
npm run dev
```

## Tests

```bash
php artisan test
```

```bash
npm test
```

```bash
npm run typecheck
```

Back-end feature tests cover authentication, ownership isolation, task/goal rules, achievements, and every admin rule. Front-end unit tests cover the date and scoring logic.

## Deploy to a server

`deploy/install.sh` sets up a fresh Ubuntu server (nginx, PHP-FPM, MySQL, Node), creates the database and `.env`, builds the app and seeds the first administrator. Run it as root on the server:

```bash
git clone https://github.com/mahmoudabuaisha/Trading-community.git /var/www/trading-community
```

```bash
cd /var/www/trading-community
```

```bash
ADMIN_EMAIL=you@example.com ADMIN_PASSWORD='choose-a-strong-password' ADMIN_NAME='Your Name' SITE_HOST=your-domain-or-ip bash deploy/install.sh
```

Later releases:

```bash
bash deploy/update.sh
```

After pointing a domain at the server, enable HTTPS with certbot (the install script prints the commands), then set `APP_URL=https://…` and `SESSION_SECURE_COOKIE=true` in `.env`.

Password-reset emails are written to `storage/logs` until SMTP is configured (`MAIL_*` in `.env`).

## Architecture

```
app/
├── Http/Controllers/Api/   thin controllers
├── Http/Requests/          all validation
├── Http/Resources/         response shapes (camelCase JSON)
├── Http/Middleware/        role gate, locale from Accept-Language
├── Models/                 relationships, casts, data-boundary rules
├── Policies/               per-model ownership checks
└── Services/               AchievementService, AdminService
resources/js/
├── views/                  one file per routed page
├── components/             reusable, no routing knowledge
├── layouts/                workspace shell (top bar, navigation drawer)
├── stores/                 Pinia: auth, workspace, admin, ui
├── data/                   the persistence seam (repository + HTTP client)
├── i18n/                   Arabic and English strings
├── lib/                    pure date / scoring / image helpers (unit tested)
└── types/                  domain contract and conventions
resources/css/              tokens.css (design tokens) + app.css
```

Conventions (see `resources/js/types/domain.ts`):

- Instants are ISO 8601 strings; calendar days are `YYYY-MM-DD`. Never a pre-formatted display string.
- Durations are whole minutes.
- Statuses, priorities, periods, roles and badge keys are machine values; Arabic and English labels live only in `resources/js/i18n`.
- Business rules live at the data boundary: a task's `completed_at` follows its status in the model, goal progress is clamped in the model, badges are awarded by a service, and admin invariants (no self-demotion, never zero admins) are enforced server-side and audited.
- Components talk to stores, stores talk to `repository`; nothing above `resources/js/data` knows the data comes from HTTP.

The visual language is documented in [`docs/DESIGN_SYSTEM.md`](docs/DESIGN_SYSTEM.md).

## Not included

- Sign in with Google (needs OAuth credentials).
- An AI assistant: the assistant tab gives rule-based suggestions computed from the member's own data.
