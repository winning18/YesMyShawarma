---
paths:
  - "app/Policies/**"
  - "app/Http/Middleware/**"
  - "routes/**"
  - "app/Providers/**"
---

# Roles and permissions

Uses `spatie/laravel-permission` with **teams enabled, where team = branch**.

Roles are always resolved in the context of a branch. A user may hold different roles at
different branches. `owner` is the only role granted across all branches implicitly.

Roles: `staff`, `rider`, `manager`, `general_manager`, `owner`, `stock_manager`.

## Permission matrix

| Permission | staff | rider | manager | general_manager | owner | stock_manager |
|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `orders.view` | ✓ | own only | ✓ | ✓ | ✓ | — |
| `orders.accept` | ✓ | — | ✓ | ✓ | ✓ | — |
| `orders.reject` | ✓ | — | ✓ | ✓ | ✓ | — |
| `orders.advance_status` | ✓ | own only | ✓ | ✓ | ✓ | — |
| `orders.assign_rider` | ✓ | — | ✓ | ✓ | ✓ | — |
| `orders.void` | — | — | ✓ | ✓ | ✓ | — |
| `orders.refund` | — | — | ✓ | ✓ | ✓ | — |
| `orders.refund_request` | ✓ | — | — | — | — | — |
| `orders.discount` | — | — | ✓ | ✓ | ✓ | — |
| `orders.create` | ✓ | — | ✓ | ✓ | ✓ | — |
| `menu.toggle_availability` | ✓ | — | ✓ | ✓ | ✓ | — |
| `menu.edit_content` | — | — | ✓ | ✓ | ✓ | — |
| `reports.view_operational` | ✓ | — | ✓ | ✓ | ✓ | — |
| `reports.view_financial` | — | — | ✓ | ✓ | ✓ | — |
| `promotions.manage` | — | — | ✓ | ✓ | ✓ | — |
| `customers.view` | — | — | ✓ | ✓ | ✓ | — |
| `reviews.moderate` | — | — | ✓ | ✓ | ✓ | — |
| `users.manage` | — | — | — | — | ✓ | — |
| `users.create_operational` | — | — | — | ✓ | — | — |
| `users.transfer_branch` | — | — | ✓ | ✓ | ✓ | — |
| `branches.manage` | — | — | — | — | ✓ | — |
| `dashboard.performance` | — | — | ✓ | ✓ | ✓ | — |
| `settings.manage` | — | — | ✓ | ✓ | ✓ | — |
| `stock.manage` | — | — | — | — | ✓ | ✓ |
| `stock.record_sale` | ✓ | — | ✓ | ✓ | ✓ | ✓ |

`general_manager` holds the exact same permission set as `manager` (see `RolesAndPermissionsSeeder`'s
`$matrix` — the two lists are meant to be kept identical apart from `users.create_operational`),
plus two behavioural differences that aren't expressible as flat permissions:

- **Multi-branch performance oversight.** A `manager`'s `dashboard.performance` is scoped to
  whichever one branch is currently selected (`BranchContext::id()`), same as every other
  permission they hold. A `general_manager`'s is scoped to *every branch they hold
  `general_manager` at* (`BranchContext::branchIdsForRole($user, 'general_manager')`),
  aggregated simultaneously — see the `dashboard.performance` section below. Every other
  permission a `general_manager` holds (orders, menu, reports, promotions, customers, working
  hours) still only ever applies at whichever one branch is currently selected via the branch
  switcher, exactly like a plain `manager` — the multi-branch aggregate is specific to the
  Performance page, not a general "acts everywhere at once" capability.
- **`users.create_operational`** (`UserManagementController::create()`/`store()`) lets a
  `general_manager` create new `staff`/`rider` accounts — something a plain `manager` cannot do
  at all. Restricted server-side, not just by hiding options in the form: the role must be
  `staff` or `rider` (never `manager`, `general_manager`, or `owner`), and the branch must be
  one the actor holds `general_manager` at themselves. A `general_manager` gets no other
  `users.*` ability — no edit, no role removal, no delete, no branch transfer of a role they
  didn't just create (`users.transfer_branch` still covers moving an *existing* staff/rider,
  same as `manager`).

The money-touching permissions — `void`, `refund`, `discount` — are deliberately
separated so they can be audited independently. Never fold them into a broader permission.

`reviews.moderate` (`ReviewManagementController`/`ReviewPolicy`) gates approving or rejecting a
customer-submitted review before it's shown publicly. Same audience and same branch-scoping
shape as `orders.refund`: a `manager` only moderates reviews at their own branch, a
`general_manager` at every branch they hold that role at, `owner` everywhere —
`ReviewPolicy::checkAtReviewBranch()` checks against the review's own `branch_id`, never the
ambient session branch, same as `RefundPolicy::checkAtRefundBranch()`. `staff` and `rider` hold
nothing here; moderating public-facing content is a business-reputation decision, not an
operational one.

`settings.manage` (`SettingsController`, `StaffMemberManagementController`) is held by
`owner`/`manager`/`general_manager` — business-wide configuration (the order reference
prefixes, `schema.md`'s Settings section, and the public "Meet our staff" roster shown on the
About page), not scoped to any branch. Unlike `branches.manage` and `users.manage` (still
owner-only), this one was deliberately opened up to `manager`/`general_manager` — the content
it gates isn't sensitive in the way branch/user management is.

`orders.refund` (`RefundController`/`RefundService`/`RefundPolicy` — see payments.md's Refunds
section for the full shape) is the one permission where `manager` and `general_manager` sit at
the *same* tier as `owner` rather than a tier below — all three can refund a customer directly
with no approval step, and can approve/deny a `staff` request. Branch-scoped like every other
order permission: a `manager` only holds it at their own branch, `general_manager` at every
branch they hold that role at. `orders.refund_request` (`staff` only — `rider` never touches
money) is the "may ask for a refund" ability: creates a `pending` request that needs an
`orders.refund` holder to approve before `staff` can complete it. `owner` shows `—` for
`orders.refund_request` in the matrix despite covering everything it allows and more:
`orders.refund`'s owner actions are gated through `Gate::authorize()`, which
`AppServiceProvider`'s `Gate::before` short-circuits for owner *before* the policy method — and
therefore before `RefundPolicy`'s own `$user->can()`/`canAny()` checks — ever runs. Owner
genuinely doesn't hold `orders.refund_request` in the seeder; they simply never reach the check
that would need it.

The Refunds sidebar page (`RefundController::index`, `RefundPolicy::viewAny` —
`orders.refund` *or* `orders.refund_request`) follows the exact same three-way branch split as
`dashboard.performance` below for `owner`/`general_manager`/`manager`: `owner` sees every
branch's refunds, `general_manager` sees every branch they hold that role at, `manager` sees
their one currently-selected branch. `staff` also reaches this page now (`orders.refund_request`
alone is enough for `viewAny`) — they get the same "one currently-selected branch" scope as
`manager` (`index()` never bypasses `BranchScope` for them either), but it's a review list, not
a queue they can act on freely: the Blade view wraps Approve/Deny in `@can('approve'|'deny',
$refund)`, which only `orders.refund` satisfies, so `staff` never sees those buttons at all —
only `Complete` on an already-`approved` request, same as everywhere else `orders.refund_request`
applies.

`users.transfer_branch` (moving a `staff`/`rider`/`manager` role assignment from one branch to
another — `UserManagementController::changeBranch()`, `UserPolicy::changeBranch()`) is a flat
permission plus relational rules that can't be expressed in the matrix alone:

- Nobody may transfer their own branch assignment, manager or owner included.
- A `manager`-role assignment can only be moved by an `owner` — a manager can transfer staff
  and riders, never another manager.
- A manager's authority is branch-scoped to the assignment's *current* branch, same pattern as
  `OrderPolicy::checkAtOrderBranch` — holding `manager` at Branch A does not let them transfer
  someone whose current assignment is at Branch B.
- `owner`-role assignments are never transferable through this action at all.

`dashboard.performance` (`PerformanceController`) is the business-overview page `owner`,
`general_manager` and `manager` all land on at `/dashboard` — but none of the three see the
same data. `owner` gets it cross-branch, aggregated across every branch, same as it's always
been (`$ignoreBranchScope = true` — unconditional, so a brand new branch shows up for owner
without needing any role row at all). `general_manager` gets it aggregated across only the
branches they hold `general_manager` at (`$scopeBranchIds`, an explicit list — never "all
branches", which would silently grow to include a branch they don't actually oversee the
moment it's created). `manager` gets their one currently-selected branch only. This is decided
by actual role (`BranchContext::hasRoleAtAnyBranch()`), never by which branch happens to be
selected — a manager who somehow had the cross-branch view would be reading other branches'
revenue. The "By branch" breakdown table is `owner`/`general_manager` only (`$crossBranch`):
there is nothing to compare when a manager can only ever see their own one branch.

On the Operations tab, `owner` and `general_manager` can additionally drill into a single
branch via the `branch` query param — `PerformanceController` only honours it when
`$crossBranch` is true, and for `general_manager` specifically only when the submitted branch
is one of their own (`$scopeBranchIds`); otherwise it's silently dropped back to the aggregate,
never trusted, so neither a plain manager nor a general_manager tampering the query string can
peek at a branch they have no role at. The drilldown and the "By branch" table both call
`OrderReportService::operationalSummary()` / `financialSummary()` per branch with an explicit
`$branchId`, never by mutating session state — same reasoning as
`PerformanceReportService::itemSales()`'s own `$branchId` param. The aggregate views
(`salesSummary()`, `itemSales()`, `operationalSummary()`) take an equivalent `$branchIds` list
for `general_manager`'s multi-branch case, alongside the existing `$ignoreBranchScope` (owner,
unconditional) and single `$branchId` (manager) — `$branchIds` wins over `$branchId` whenever
both could apply.

`UserPolicy::changeBranch()` blocks `owner`, `manager` **and** `general_manager` role rows from
being transferred through `UserManagementController::changeBranch()` by anyone but `owner` (who
bypasses the policy entirely via `Gate::before`) — a `general_manager` outranks a plain
`manager`, so a `manager` actor moving one of their assignments is exactly as wrong as moving
another `manager`'s.

`dashboard.working-hours.*` (`WorkingHoursController`) is its own sidebar item — the weekly
opening schedule, not part of Reports and invoices. Deliberately reuses `reports.view_financial`
rather than a dedicated permission: that permission is already exactly owner+manager (see the
matrix above), which is the audience asked for, so a new permission would only duplicate it. Its
routes are kept out of the `dashboard.reports.*` name prefix on purpose — the "Reports and
invoices" sidebar link's `:active` check is the wildcard `routeIs('dashboard.reports.*')`, and
sharing that prefix would make it light up as active on this page too. Same owner/manager split
as `dashboard.performance`: owner picks any branch via a `branch` selector
(`BranchContext::selectableBranchesFor()`), manager is pinned to `BranchContext::id()`. The
controller ignores a submitted `branch` value for anyone who isn't owner, so a manager can
never write another branch's schedule by tampering with the form field.

## Enforcement

**Branch scoping is enforced by a global scope on the model, not by `where` clauses in
queries.** Apply it to `Order`, and to every other branch-owned model.

One forgotten filter in one report leaks another branch's revenue. Model-level scoping means
you cannot forget.

Riders get a further restriction: they only ever see orders where `rider_id = auth()->id()`.
There is no claimable pool — see orders.md's rider assignment section.

## Stock management

`stock.manage` (create/edit stock items, set quantities and low-stock thresholds) is
`owner`-only in the base matrix, plus the dedicated `stock_manager` role. `stock.record_sale`
(log a sale against a branch's stock) goes to everyone who works a branch day-to-day —
`staff`, `manager`, `general_manager`, `owner`, `stock_manager` — deliberately excluding
`rider`, who never touches ingredient stock.

`stock_manager` exists because this app has **no mechanism to grant a permission to one
specific user outside a role** (`givePermissionTo()` is never called anywhere except this
seeder) — "owner can assign anyone to manage stock" therefore has to be a role, not a per-user
override. It's listed in `UserManagementController::ROLES` and `CreateUserRequest`, so it's
assignable/creatable through the exact same roster UI as every other role, branch-scoped the
same way — assigning it at one branch only grants stock rights at that branch, never every
branch, same as `manager`.

`StockItem` carries its own `branch_id` and the standard `BranchScope`, same as every other
branch-owned model. `StockMovement` (the append-only ledger backing every restock/sale,
mirroring `order_events`) carries no `branch_id` of its own — it's reached only through its
`StockItem`.

## Guards

- `web` guard — `users` table (staff, riders, managers, owners)
- `customer` guard — `customers` table
- `sanctum` — API tokens for the future mobile app

**Never authenticate a customer against the `users` table or vice versa.** Any permission
check reached by a customer-guard request is a bug.

## Adding permissions

New permissions go in a seeder, not a migration, so they can be re-run. Update the matrix
above in the same commit — a permission that exists in code but not in this table will drift.
