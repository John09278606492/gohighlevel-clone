# LeadEngine REST API (v1)

Each sub-account has its own API keys. Create them under **Settings → API keys** (admins only); the key is shown once.

```
Authorization: Bearer le_xxxxxxxxxxxxxxxx
Accept: application/json
```

Base URL: `https://your-domain/api/v1`. A key only ever sees its own sub-account's data. Requests are limited to 120 per minute per key. Errors use Laravel's standard JSON: `401` for a missing or revoked key, `404` for a record outside the sub-account, and `422` with an `errors` object for validation.

## Contacts
| Method | Path | Notes |
|---|---|---|
| GET | `/contacts` | Paginated (`per_page` up to 100). Filters: `query` (name, email, phone), `email`, `tag`, `updated_since` |
| POST | `/contacts` | Upsert by email or phone. Needs `email` or `phone`. Returns `201` when created, `200` when an existing contact was updated |
| GET | `/contacts/{id}` | |
| PUT/PATCH | `/contacts/{id}` | |
| DELETE | `/contacts/{id}` | |
| POST | `/contacts/{id}/tags` | `{"tags": ["vip"]}`. Fires the *Tag added* workflow trigger |
| DELETE | `/contacts/{id}/tags` | `{"tags": ["vip"]}` |
| POST | `/contacts/{id}/notes` | `{"body": "Called, left voicemail"}` |
| POST | `/contacts/{id}/messages` | `{"channel": "email"\|"sms", "subject": "...", "body": "..."}`. Merge tags work. Opted-out contacts are skipped |

Contact fields: `first_name`, `last_name`, `email`, `phone`, `company`, `type` (`lead`/`customer`), `source`, `address`, `city`, `country`, `date_of_birth`, `dnd`, `tags` (create only), and `custom_fields` keyed by the custom field's key, such as `{"custom_fields": {"insurance_provider": "Delta"}}`.

```bash
curl -X POST https://your-domain/api/v1/contacts \
  -H "Authorization: Bearer le_..." -H "Accept: application/json" \
  -d first_name=Ada -d email=ada@example.com -d "tags[]=webinar"
```

## Opportunities
| Method | Path | Notes |
|---|---|---|
| GET | `/pipelines` | Pipelines with their stages |
| GET | `/opportunities` | Filters: `pipeline_id`, `status`, `contact_id` |
| POST | `/opportunities` | `name`, `pipeline_id`, and optionally `pipeline_stage_id` (defaults to the first stage), `contact_id`, `value`, `status`, `source` |
| PUT/PATCH | `/opportunities/{id}` | `name`, `pipeline_stage_id`, `value`, `status` (`open`, `won`, `lost`, `abandoned`) |

## Calendars and appointments
| Method | Path | Notes |
|---|---|---|
| GET | `/calendars` | Includes each calendar's public `booking_url` |
| GET | `/appointments` | Filters: `from`, `to`, `status` |
| POST | `/appointments` | `calendar_id`, `contact_id`, `starts_at`, and optionally `ends_at`, `title`, `notes` |
| PUT/PATCH | `/appointments/{id}` | `status`, `starts_at`, `ends_at`, `notes` |

## Invoices
| Method | Path | Notes |
|---|---|---|
| GET | `/invoices` | Filter: `status`. Each invoice includes its public payment `url` |

## Webhooks into LeadEngine
These are under **Settings → Messaging** for each sub-account:
- **Twilio SMS**: `POST /webhooks/twilio/{sub-account}/sms` (inbound SMS; the signature is checked against the sub-account's auth token) and `/status` (delivery receipts). STOP and START are handled automatically.
- **Inbound email**: `POST /webhooks/email/{sub-account}/{inbound_secret}` with `from`, `subject` and `text` (or `html`); Mailgun, Postmark and SendGrid field names are also accepted.
- **Stripe**: `POST /webhooks/stripe/{sub-account}` with the `checkout.session.completed` event. Payments are verified by re-fetching the session from Stripe.
