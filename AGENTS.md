# Petty Cash Digitaliz - OpenCode Project Rules

## Project

This is a Laravel application for Digitaliz internal Petty Cash & Reimbursement.

Core workflow:

Requester
-> Admin
-> Finance
-> Completed

Roles:

* requester
* admin
* finance
* head

## Critical Dependency Rule

DO NOT install, remove, update, upgrade, downgrade, or move any dependency without explicit developer approval.

Never run:

* composer require
* composer update
* composer remove
* npm install
* npm uninstall
* npm update

unless explicitly instructed by the developer.

Always inspect these files before making implementation decisions:

* composer.json
* composer.lock
* package.json
* package-lock.json
* .env.example
* existing migrations
* existing models
* existing controllers
* existing authentication
* existing authorization
* existing frontend stack

Prefer:

1. Existing dependencies
2. Laravel native features
3. PHP native features
4. Existing frontend libraries
5. New dependency only after approval

## Do Not Change Stack

Do not replace:

* Laravel with another backend framework
* Blade with React/Vue
* Tailwind with Bootstrap
* MySQL with another database
* existing authentication system
* existing permission system

unless explicitly approved.

## Database (Canonical — Locked)

The existing database schema is canonical and is the source of truth.
The active application workflow already uses these tables:

* users
* budget_codes
* requests
* request_files
* request_events
* wa_logs
* settings

Do NOT create duplicate request/file/history/settings tables.
The following tables are NOT canonical and must NOT be created:

* petty_cash_requests
* request_attachments
* request_budget_details
* request_status_histories
* activity_logs
* notifications
* app_settings

Do not create additional tables without a clear technical reason and an approved migration plan.

Notes:

* `request_events` is the canonical status history.
* `wa_logs` is the canonical WhatsApp/notification log (there is no `notifications` table).
* Budget assignment uses `requests.budget_code` / `requests.budget_description` (there is no `request_budget_details` table).

## Request Status (Locked)

Preserve the existing RequestStatus values:

* pending_review
* needs_revision
* rejected
* processing
* done

Do NOT introduce:

* draft
* menunggu_validasi
* perlu_revisi
* ditolak
* diproses_finance
* selesai

Indonesian text (Menunggu Validasi Admin, Perlu Revisi, Ditolak, Diproses Finance, Selesai) must remain presentation labels only, as defined in `App\Enums\RequestStatus::label()`.

Do not create additional workflow statuses without approval.

## Business Rules

1. Requester can only see their own requests.
2. Requester cannot see or edit budget information.
3. Admin assigns budget code and description.
4. Admin cannot approve without budget assignment.
5. Revision requires a reason.
6. Rejection requires a reason.
7. Finance cannot change budget information.
8. Finance cannot complete a request without official transfer proof.
9. Every status change must create a status history record (`request_events`).
10. Important actions must create an activity log (`request_events` + `wa_logs`).
11. Request number must be unique.
12. Completed requests must not be completed twice.

## Architecture

Do not put complex business logic directly inside controllers.

Prefer:

Controller
-> Form Request
-> Service
-> Model

Authorization is implemented with Laravel Gates in `App\Providers\AppServiceProvider`.
Do NOT replace Gate authorization with Policies, Spatie Permission, or any other permission system without an approved migration plan.

## External Integrations

Google Drive and WhatsApp must be isolated behind service abstractions.

Do not install external SDKs without approval.

If an integration requires a missing dependency, STOP and report:

DEPENDENCY REQUIRED

Do not install it automatically.

## Reporting

Support:

* CSV export
* PDF report

Do not add a PDF package unless it already exists or the developer explicitly approves it.

## Development Process

Work incrementally.

Do not implement the entire project in one step.

Recommended order:

1. Authentication and roles
2. Database
3. Requester workflow
4. Admin workflow
5. Finance workflow
6. Status tracking
7. Audit log
8. File handling
9. Notifications
10. Google Drive
11. Reporting
12. Head dashboard

## Verification

After meaningful changes:

* run relevant tests
* verify migrations
* verify authorization
* verify validation
* verify workflow transitions

Do not claim a feature is complete unless it has been tested.

## Important

Build inside the existing project.

Extend existing architecture.

Do not replace existing architecture without approval.

Do not redesign the application.

Do not invent requirements.

Do not add dependencies automatically.

Architecture lock (final):

* Existing database schema is canonical.
* Do not create duplicate request/file/history/settings tables.
* Preserve existing RequestStatus values.
* Do not add dependency without explicit approval.
* Do not replace Gate authorization without an approved migration plan.
