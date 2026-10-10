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
| Reporting | Dashboard | Filament stats, chart and table widgets (core) |

### Custom workflow actions (`app/Flow/Actions`)
- **Send message to contact**: sends an email or SMS and logs it in the contact's conversation. Contacts marked Do Not Disturb are skipped.
- **Create opportunity**: adds the contact to a pipeline stage.
- **Move opportunity**: changes an opportunity's stage or marks it won or lost.

The workflow templates in `resources/flow-templates` are New lead nurture, Appointment booked, No-show follow-up and Deal won. You'll find them under New workflow → Template.

## Setup

Requirements: PHP 8.3+ with `bcmath`, `intl` and `zip`; Composer; Node 20+.

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite          # or point DB_* at MySQL/Postgres
php artisan migrate --seed
php artisan storage:link
npm install && npm run build
php artisan serve
```

To run workflows that include waits, and the scheduled triggers, also start:

```bash
php artisan queue:work
php artisan schedule:work     # runs packstub-flow:cron every minute
```

### Demo logins (from the seeder)
| User | Password | Access |
|---|---|---|
| `admin@example.com` | `password` | Agency view (`/agency`) and every sub-account |
| `staff@example.com` | `password` | Only the Bright Smile Dental sub-account |

Pages to try after seeding:
- `/app/bright-smile-dental`: the sub-account dashboard
- `/book/bright-smile-dental/discovery-call`: the public booking page
- `/p/bright-smile-dental/free-consultation`: a funnel step with an embedded form

## Tests

```bash
php artisan test
```

The tests load every screen, check that staff can't reach other sub-accounts or the agency view, book through the public page, submit a form and check the contact it creates, drag a pipeline card, and run a workflow end to end, including a check that workflows stay inside their own sub-account.

## Notes and next steps
- **Email** goes through Laravel's mailer, so set `MAIL_*` in `.env`. **SMS** is logged as "queued" in the inbox; connect a provider in `App\Services\MessageSender`, or use Flow's built-in Twilio "Send SMS" action.
- Inbound email and SMS need a webhook from your provider that creates an `inbound` `Message`. Until then, use "Log inbound message" in a conversation.
- Tag names are shared across sub-accounts, because spatie/laravel-tags is global. Values are per contact.
- `public/build` isn't committed, so run `npm run build` after cloning.
