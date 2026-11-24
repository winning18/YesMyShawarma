# MVP cut-over: Yes My Shawarma becomes an ordinary Express POS install

The plan for MVP release day (owner, 2026-11-24). Work through it top to bottom; tick each box.
Owner = Solusys Digital's owner. Tech = whoever runs the server steps (with Claude).

## What changes

| | Before | After |
|---|---|---|
| Yes My Shawarma's POS (staff, tills, dashboard, riders) | `yesmyshawarma.com/login` | `yesmyshawarma.pos.solusysdigital.com` |
| Yes My Shawarma's website | `yesmyshawarma.com` | `yesmyshawarma.com` (unchanged, connected as its own domain) |
| Its code and database | `/var/www/yes-my-shawarma`, `yes_my_shawarma` | `/var/www/expresspos-yesmyshawarma`, `expresspos_yesmyshawarma` |
| Code it runs | `Main` (as of 2026-11-15 plus fixes) | the released Express POS (`Main` after the merge) |
| Shared staging | `staging.yesmyshawarma.com` | `staging.pos.solusysdigital.com` (old address redirects) |
| What each deploy updates | `Main`: Yes My Shawarma only; `staging`: staging and client installs | `Main`: every live install; `staging`: shared staging and sandboxes |

**Unchanged for customers:** the website address, their accounts and order history, Google
results, links in SMS already sent, the home-screen app, Paystack checkout.

**Things that change for staff:** a new sign-in address; each till device is set up as a counter
again; push notifications switched on again; riders use the new link.

**No DNS changes are needed:** `*.pos.solusysdigital.com` already points at the server, and so
does `yesmyshawarma.com`.

**Why move to a new install:** the alternative is renaming the live one in place. Moving keeps
the old install and database untouched as a ready rollback, and puts Yes My Shawarma on exactly
the layout every other client uses, so the sandbox, backups and the portal work for it. The data
is small (about 41 MB of database and 31 MB of photos), so the move takes minutes.

## Before the day: code (each needs the owner's go-ahead, on staging)

- [ ] **Two update lists.** Live installs (`/etc/expresspos/installs`) are updated by the `Main`
      deploy. Shared staging and sandboxes (`/etc/expresspos/staging-installs`) are updated by the
      `staging` deploy. Changes: `deploy-staging.yml`, `deploy.yml` (stops deploying to
      `/var/www/yes-my-shawarma`), `sandbox.sh` and `provision.sh` (register in the right list),
      and moving `expresspos-staging` to the staging list. Until release day, client installs
      stay on the staging code, as now.
- [ ] **Register an existing install in Express POS Admin.** Yes My Shawarma never went through
      onboarding, so it needs a client record (slug `yesmyshawarma`, its modules, brand colours,
      logo, `yesmyshawarma.com` as its website domain). It's a small portal command, so keys,
      brand and sandboxes can be managed from the portal like any client.
- [ ] **Check `add-website-domain.sh` takes over an address already served:** it replaces the
      old `yes-my-shawarma-domain` nginx site and reuses its certificate.
- [ ] **Write a move script** (`deploy/move-yes-my-shawarma.sh`) that does the day-of server steps
      below in order and stops at the first problem, so the night is one command plus checks.
- [ ] Full test suite green on staging, and the staging deploy green.

## Before the day: rehearsal (about 3 days before)

- [ ] Run the move script against a **copy**, into a gated temporary install (e.g.
      `ymsrehearsal.pos.solusysdigital.com`), with customer data scrubbed as in a sandbox.
- [ ] Check on the copy:
  - every migration ran;
  - Business profile, colours (background black), logo, Classic look;
  - every module on, including Tables and Tax (Tax with no lines, so nothing charged);
  - menu, prices, branches, hours, promotions;
  - order history and reports match production for last week;
  - counters, printers, PINs;
  - rider cash-ups;
  - the Paystack webhook answering at `yesmyshawarma.com/api/…`.
- [ ] Time the run. Note anything that needed a hand fix and put it into the script.
- [ ] Delete the rehearsal install.

## Before the day: people and decisions

- [ ] **Owner picks the date and time:** after closing, on a quiet night (e.g. 23:00 to midnight),
      with someone free the next morning at opening.
- [ ] **Owner decides** the POS mode for Yes My Shawarma: Tables and Quick sale (`both`, the
      default), or Quick sale only.
- [ ] **Staff briefing (Owner), a day or two before:**
  - the new sign-in address (bookmark it, add it to the home screen);
  - each till device must be set up again as a counter, by a manager, in the morning;
  - PIN sign-in works again once a till is set up;
  - switch push notifications on again;
  - riders' new link;
  - rider cash-ups now take the food money at a till (it changes what each till expects);
  - what Tables and Dine in look like now.
- [ ] Tell the managers who will set up the counters at opening (Settings → Counters, "Use this
      device as…").
- [ ] Print Agent PCs: nothing to do. The agent's address (`yesmyshawarma.com`) keeps working,
      because the website domain answers the agent and Paystack. Updating it to the new address
      can wait.

## The night

Start only if the rehearsal passed. Note the time at each step.

1. - [ ] **Final backup:** `sudo /usr/local/bin/expresspos-backup`; confirm a fresh
         `yes_my_shawarma` dump exists. Copy `storage/app` aside.
2. - [ ] **Close the old install:** `php artisan down` in `/var/www/yes-my-shawarma`, so customers
         and staff see a short maintenance page. Leave it otherwise untouched: it's the rollback.
3. - [ ] **Run the move script:**
         - new install `yesmyshawarma` on the release code;
         - the database dumped and imported, all of `storage/app` copied;
         - the old `APP_KEY` and every service key (Paystack, Arkesel, Web Push VAPID, Resend,
           Sentry, Turnstile) copied into the new `.env`;
         - migrations run;
         - `APP_URL` set to the POS address;
         - `yesmyshawarma.com` and `www.` connected as its website domain;
         - `staff.`, `stock.` and `rider.yesmyshawarma.com` redirected to the new POS address;
         - registered in the live update list and the nightly backups.
4. - [ ] **Release the code:** merge `staging` into `Main` and push. Watch the `Main` deploy
         update the live installs (Yes My Shawarma and De Boyes Kitchen) with the same code.
5. - [ ] **Checks before saying go** (Tech and Owner together, on a phone and a computer):
   - [ ] `yesmyshawarma.com` opens the website; the menu, a dish and the cart work.
   - [ ] A test order with Paystack (smallest amount) completes, and the webhook marks it paid.
   - [ ] `yesmyshawarma.pos.solusysdigital.com` signs in (owner); the dashboard, order board and
         reports show the real history.
   - [ ] A POS order on a test counter reaches the kitchen board live; receipt and kitchen
         ticket print through the agent.
   - [ ] `yesmyshawarma.com/login` sends staff to the new POS address; customer pages on the
         POS address send customers to the website.
   - [ ] The rider app opens at the new address; `rider.yesmyshawarma.com` goes there.
   - [ ] An SMS (order update) arrives, with a link to `yesmyshawarma.com`.
   - [ ] Cancel and refund the test orders.
6. - [ ] **Go or roll back.** Go: leave the old install down. Roll back: see below.
7. - [ ] **Staging:** `staging.yesmyshawarma.com` redirects to `staging.pos.solusysdigital.com`.

## Rolling back

Possible until real orders arrive on the new install. After that, orders taken on the new one
would have to be entered again by hand, so decide by opening time.

1. `php artisan down` on the new install.
2. Put back the old nginx sites (`yes-my-shawarma-domain` and the subdomain redirects, kept as
   copies by the move script) and reload nginx.
3. `php artisan up` on `/var/www/yes-my-shawarma`.
4. Revert the `Main` merge, so the next deploy doesn't touch anything; then work out what went
   wrong before trying again.

## The morning after

- [ ] At opening: managers set up each till device as a counter; till staff sign in with Staff
      ID and PIN; the first real orders print and reach the board.
- [ ] Tech watches the error log and Sentry through the first service.
- [ ] Owner checks the first cash-ups against the Sales report.

## The week after

- [ ] Search Console and Google: no change expected (the website address didn't move); glance at
      it after a few days.
- [ ] Remove old push subscriptions from before the move once staff have switched push on again,
      so nobody gets each alert twice.
- [ ] Update the Print Agent and the Paystack webhook to the new address, for tidiness only.
- [ ] After one week, if all is well: stop and remove the old `yes-my-shawarma` and
      `yes-my-shawarma-staging` services, nginx sites and folders.
- [ ] After two weeks: drop the old `yes_my_shawarma` database (its nightly dumps stay for
      `KEEP_DAYS`) and take it out of `backup.conf`.
- [ ] Update CLAUDE.md: remove the "until the MVP" release routine and the fix route, and record
      the new layout.
