# LeadEngine: a GoHighLevel-style CRM on Laravel + Filament

An agency CRM in the style of GoHighLevel, built on **Laravel 13** and **Filament 5**. Apart from Laravel and Filament core, it uses only free plugins from the [Filament plugin directory](https://filamentphp.com/plugins?price=free).

## What's inside

| GoHighLevel feature | Where it lives | Built with |
|---|---|---|
| Agency view with sub-accounts | `/agency`, plus a sub-account switcher in `/app` | Filament multi-tenancy (core) |
| Contacts: tags, notes, tasks, DND, custom fields | Contacts | Filament resources, `spatie/laravel-tags` + Filament's official Spatie Tags plugin |
| Conversations inbox (email, SMS, notes) | Conversations | Custom: `ViewConversation` page and `MessageSender` service |
| Pipelines and opportunities as a drag-and-drop kanban | Pipelines | **Flowforge** (Relaticle) |
| Calendar with drag-to-reschedule | Calendar | **Calendar** (Guava) |
| Public booking pages | `/book/{sub-account}/{calendar}` | **Date Time Slots** (WooServ) |
| Forms and surveys that create contacts | Forms | **Form Builder** (Packstub) and the `CreateContactFromSubmission` sink |
| Funnels and websites | Funnels & websites, Pages, `/p/{sub-account}/{slug}` | **Page Builder** (Carl Janzell) with `[form]`, `[booking]` and `[next_step]` shortcodes |
| Workflows and automations | Automation → Workflows | **Flow** (Packstub), with custom CRM actions and GoHighLevel-style templates |
| Email templates | Agency → Email templates | **Fin Mail** (Finity Labs) |
| Dashboard | Dashboard | Filament stats, chart and table widgets (core) |
| Reporting with date ranges (revenue, leads, appointments, pipeline, campaigns) | Reporting → Reports | Filament dashboard filters and chart widgets (core) |
| Custom fields and custom values (`{{ custom_values.promo_code }}`) | Settings → Custom fields / Custom values | Custom |
| Smart lists (saved contact filters, shown as tabs) | Contacts | Custom `SmartList::apply` |
| Contact activity timeline, CSV import and export, bulk actions | Contacts | Custom `Activity` log and `ContactCsv` |
| Email and SMS campaigns with scheduling, open/click tracking and unsubscribe | Marketing → Campaigns | Custom `CampaignSender` and a queued job |
| Trigger links (tracked short links that tag contacts and fire workflows) | Marketing → Trigger links | Custom |
| Social planner (schedule posts, publish through a webhook such as Zapier or Make) | Marketing → Social planner | Custom `SocialPublisher` |
| Products, invoices with a public pay page, Stripe Checkout, manual payments | Payments | Custom, using the Stripe REST API with no SDK |
| Documents and e-signature (proposals and contracts) | Sales → Documents | Custom public signing page |
| Reputation: review requests, a happy/unhappy review funnel, replies, a `[reviews]` widget | Marketing → Reviews | Custom |
| Memberships and courses with a student portal and progress tracking | Sites → Courses | Custom |
| Website chat widget (one script tag) that lands in Conversations | Settings → Chat widget, `[chat]` shortcode | Custom |
| Two-way SMS through Twilio (STOP/START handled) and inbound email | Conversations | Custom `Twilio` and `InboundMessages` services |
| Team members and roles per sub-account | Settings → Team | Custom |
| REST API with per-sub-account keys | Settings → API keys, [docs/API.md](docs/API.md) | Custom |
| Snapshots (copy a sub-account's setup into another) | Agency → Snapshots | Custom `SnapshotService` |
| White-label branding (name, logo, color) | Agency → Branding | Custom |

### Custom workflow actions (`app/Flow/Actions`)
- **Send message to contact**: sends an email or SMS and logs it in the contact's conversation. Contacts marked Do Not Disturb are skipped.
- **Create opportunity**: adds the contact to a pipeline stage.
- **Move opportunity**: changes an opportunity's stage or marks it won or lost.
- **Add tag / Remove tag / Update contact / Create task / Send review request / Give course access**.

The **CRM event** trigger starts a workflow on: tag added or removed, form submitted, inbound message, appointment status changed, opportunity stage or status changed, campaign email opened, invoice paid, payment received, document signed, review received, trigger link clicked, course enrolled or completed, and unsubscribed. Each event can be filtered, such as by a specific tag.

The workflow templates in `resources/flow-templates` are New lead nurture, Appointment booked, No-show follow-up and Deal won. You'll find them under New workflow → Template.

## Setup

Requirements: PHP 8.3+ (with the `bcmath`, `intl`, `zip` and `pdo_sqlite` extensions), Composer and Node.js. On Windows, [Laragon](https://laragon.org) or [Herd](https://herd.laravel.com) installs all three.

### One click
| | Windows | Mac / Linux |
|---|---|---|
| **Set up and open the app** (also resets the demo data) | double-click `setup.bat` | `./setup.sh` |
| **Start it again later** | double-click `start.bat` | `./start.sh` |
| **Run the tests** | double-click `test.bat` | `./test.sh` |

Setup checks for missing tools and PHP extensions, installs everything, creates a SQLite database with demo data, builds the assets, then starts the app and opens http://127.0.0.1:8000/app/login. The start script also runs the queue worker and scheduler, which workflows need for their waits and scheduled triggers.

### By hand
```bash
composer run setup   # install, .env, SQLite database, demo data, build assets
composer run test    # run the tests
php artisan serve    # plus `php artisan queue:work` and `php artisan schedule:work` for workflows
```

### Demo logins (from the seeder)
| User | Password | Access |
|---|---|---|
| `admin@example.com` | `password` | Agency view (`/agency`) and every sub-account |
| `staff@example.com` | `password` | Only the Bright Smile Dental sub-account |

**Moving between the two views.** The agency view (`/agency`) is the super-admin side: every sub-account, the team, and email templates. To reach it from a sub-account, use **Agency view** in the sidebar or the avatar menu. To go back, use **Back to <sub-account>** at the top of the agency sidebar (it remembers the last one you opened), the avatar menu, or **Switch to** on any row under Sub-accounts. Inside `/app`, the sub-account name at the top of the sidebar switches between sub-accounts.

Pages to try after seeding:
- `/app/bright-smile-dental`: the sub-account dashboard
- `/book/bright-smile-dental/discovery-call`: the public booking page
- `/p/bright-smile-dental/free-consultation`: a funnel step with an embedded form

## Tests

The tests load every screen (including the agency and public pages), check that staff can't reach other sub-accounts or the agency view, book through the public page, submit a form and check the contact it creates, drag a pipeline card, and run a workflow end to end, including a check that workflows stay inside their own sub-account. They also cover invoice payments (manual and Stripe), campaign sending and tracking, trigger links, the review funnel, document signing, courses, the chat widget, Twilio webhooks, API key scoping, snapshots and unsubscribing.

## Running in production
- Run `php artisan queue:work` (campaigns are sent on the queue) and `php artisan schedule:work`, or a cron entry for `php artisan schedule:run`, which publishes scheduled campaigns and social posts every minute.
- **Email**: set `MAIL_*` in `.env`. Each sub-account can set its own from name and address under Settings → Messaging.
- **SMS**: add a Twilio account SID, auth token and number under Settings → Messaging, then point the Twilio number's webhook at the URL shown there. Without Twilio, SMS is logged as "queued".
- **Payments**: add a Stripe secret key under Settings → Payments and point a Stripe webhook (`checkout.session.completed`) at the URL shown there. Without Stripe, record payments by hand.
- **Inbound email**: point your provider's inbound parse (Mailgun, Postmark, SendGrid) at the URL under Settings → Messaging.
- `public/build` isn't committed, so run `npm run build` after cloning.

## Not included
Telephony (calling, IVR, voicemail drops), affiliate manager, communities, the ad manager, native Facebook/Instagram/Google Business Profile connections (social posts go out through a webhook instead) and the mobile app. Tag names are shared across sub-accounts, because spatie/laravel-tags is global; which contacts have them is per sub-account.
