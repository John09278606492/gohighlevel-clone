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

Pages to try after seeding:
- `/app/bright-smile-dental`: the sub-account dashboard
- `/book/bright-smile-dental/discovery-call`: the public booking page
- `/p/bright-smile-dental/free-consultation`: a funnel step with an embedded form

## Tests

The tests load every screen, check that staff can't reach other sub-accounts or the agency view, book through the public page, submit a form and check the contact it creates, drag a pipeline card, and run a workflow end to end, including a check that workflows stay inside their own sub-account.

## Notes and next steps
- **Email** goes through Laravel's mailer, so set `MAIL_*` in `.env`. **SMS** is logged as "queued" in the inbox; connect a provider in `App\Services\MessageSender`, or use Flow's built-in Twilio "Send SMS" action.
- Inbound email and SMS need a webhook from your provider that creates an `inbound` `Message`. Until then, use "Log inbound message" in a conversation.
- Tag names are shared across sub-accounts, because spatie/laravel-tags is global. Values are per contact.
- `public/build` isn't committed, so run `npm run build` after cloning.
