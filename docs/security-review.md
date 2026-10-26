# Security review: invoicing back office (sample report)

*The app is fictional, built for this demo: a Filament 5 admin panel on Laravel 13 of the kind AI coding tools generate. Review by an AI agent (Claude), Nightly Works.*

**Scope**: the Filament panel (`app/Providers/Filament`, `app/Filament`), the user model's panel access, and the policies. Reviewed on a copy of the code; no live system was touched.
**Method**: source read; each suspected flaw written as an automated attack (`tests/Feature/SecurityReviewTest.php`) and run against the code as delivered (`scripts/prove-flaws.sh`: every attack works), then fixed one commit at a time until the same tests pass.
**Result**: 4 findings, 2 high. All fixed, each with a test that fails if the flaw comes back.

## Summary for the owner

Anyone on the internet can create an account and enter the back office, and a support agent can make themselves an admin. A support agent can also refund invoices and mark them paid, although the rules you wrote say only admins may. The fixes are small: about 50 lines, most of them a new policy class. The general rule behind three of the four: in Filament, a hidden button is not a permission; the server must check it.

## Findings

### 1. Anyone can register and enter the back office (high)

**What**: the panel has `->registration()` on, and `User::canAccessPanel()` returns `true` for every user.
**Impact**: a stranger signs up at `/admin/register`, opens Users (finding 2: no policy), sees every user's email and can give themselves the admin role. Invoices stay closed to them only because `InvoicePolicy` happens to check the role.
**Fix**: registration off; `canAccessPanel()` lets in only the `admin` and `support` roles.
**Verified**: `/admin/register` is 404; a user without a role gets 403; support and admin get in (test 1).

### 2. Support staff can promote themselves to admin (high)

**What**: `UserResource` has no policy. Without strict authorization, Filament allows every operation on a model that has no policy.
**Impact**: any support agent opens Users, edits their own role to `admin`, and has full control.
**Fix**: `UserPolicy` (admins only), and `->strictAuthorization()` on the panel, so a future resource without a policy throws an error instead of allowing everything.
**Verified**: support gets 403 on the users list and edit page; an admin can still change roles (test 2).
**Also fixed**: the users' bulk delete checked only `deleteAny()`, so an admin could tick their own row and delete their account; it now checks each record against `delete()` (bulk delete test).

### 3. The refund action ignores the invoice policy (medium)

**What**: `InvoicePolicy` lets only admins update invoices, but the custom `refund` table action has no authorization. Filament checks policies for its built-in actions (edit, delete), not for custom ones. The button shows for support too.
**Impact**: support can refund any paid invoice, and so could anyone who sends the same two Livewire calls with the button hidden.
**Fix**: `->authorize('refund')` on the action and a `refund()` policy method (admins, paid invoices only).
**Verified**: the two Livewire calls sent as support leave the invoice paid, and the button is hidden; an admin's refund works (test 3).

### 4. The inline status column bypasses the policy (medium)

**What**: the invoice list has an editable `SelectColumn` for the status. Editable table columns do not go through the resource's policies; they only respect `disabled()` and `hidden()`.
**Impact**: support can set any invoice to `paid` from the list, or by sending the `updateTableColumnState` call directly.
**Fix**: the column is disabled when the user cannot `update` the invoice; Filament refuses the update on the server for a disabled column.
**Verified**: the direct call as support leaves the status unchanged; an admin's change works (test 4).

Not affected: built-in edit and delete actions, which Filament checks against `InvoicePolicy`; support still reads the invoice list (test 5).

## Checked next in a full review

Bulk actions (Filament checks `deleteAny()`, not each record), relation managers, global search results, file uploads (disk and visibility), exports, multi-tenancy scoping if enabled, and the login throttling and password reset settings.
