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

Roles: `staff`, `rider`, `manager`, `owner`.

## Permission matrix

| Permission | staff | rider | manager | owner |
|---|:--:|:--:|:--:|:--:|
| `orders.view` | ✓ | own only | ✓ | ✓ |
| `orders.accept` | ✓ | — | ✓ | ✓ |
| `orders.reject` | ✓ | — | ✓ | ✓ |
| `orders.advance_status` | ✓ | own only | ✓ | ✓ |
| `orders.assign_rider` | ✓ | — | ✓ | ✓ |
| `orders.void` | — | — | ✓ | ✓ |
| `orders.refund` | — | — | — | ✓ |
| `orders.discount` | — | — | ✓ | ✓ |
| `orders.create` | ✓ | — | ✓ | ✓ |
| `menu.toggle_availability` | ✓ | — | ✓ | ✓ |
| `menu.edit_content` | — | — | ✓ | ✓ |
| `reports.view_operational` | ✓ | — | ✓ | ✓ |
| `reports.view_financial` | — | — | ✓ | ✓ |
| `promotions.manage` | — | — | ✓ | ✓ |
| `customers.view` | — | — | ✓ | ✓ |
| `users.manage` | — | — | — | ✓ |
| `branches.manage` | — | — | — | ✓ |
| `dashboard.performance` | — | — | — | ✓ |

The money-touching permissions — `void`, `refund`, `discount` — are deliberately
separated so they can be audited independently. Never fold them into a broader permission.

## Enforcement

**Branch scoping is enforced by a global scope on the model, not by `where` clauses in
queries.** Apply it to `Order`, and to every other branch-owned model.

One forgotten filter in one report leaks another branch's revenue. Model-level scoping means
you cannot forget.

Riders get a further restriction: they only ever see orders where `rider_id = auth()->id()`.
There is no claimable pool — see orders.md's rider assignment section.

## Guards

- `web` guard — `users` table (staff, riders, managers, owners)
- `customer` guard — `customers` table
- `sanctum` — API tokens for the future mobile app

**Never authenticate a customer against the `users` table or vice versa.** Any permission
check reached by a customer-guard request is a bug.

## Adding permissions

New permissions go in a seeder, not a migration, so they can be re-run. Update the matrix
above in the same commit — a permission that exists in code but not in this table will drift.
