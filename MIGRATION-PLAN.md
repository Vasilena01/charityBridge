# CharityBridge Refactor Plan

This document is the step-by-step plan to migrate CharityBridge from its current
shape (static-HTML + PHP API endpoints + PHP `$_SESSION` files + per-feature
migration scripts) to the target shape: **fully server-rendered PHP MVC, MySQL,
stateless signed tokens in HttpOnly cookies with a DB-backed revocation list,
single `.sql` schema, Docker-first, subfolder-aware, `.env`-driven, comment-free**.

Each numbered section is **one requirement**. Work them top-to-bottom — the
later steps assume the earlier ones are done.

### Confirmed answers from the user (these resolve the open questions in v1 of this plan)

1. **Database:** MySQL (not SQLite). All references to a SQLite fallback are removed from this plan.
2. **`public/` directory:** stays. The directive is "stop using `.html` files and migrate to MVC", not "delete the folder". `public/` remains the web-server docroot. Anything in it that is still relevant after the migration (the `assets/css/`, `assets/images/` directories, the favicon if any) stays in place. Things that are obsoleted by the migration (`*.html`, `router.php`, the page-glue JS files in `assets/js/`) are removed.
3. **Server-rendered:** every page is fully server-rendered. There is **no client-side JSON API** anymore. Forms submit via traditional HTTP POST and the server responds with a redirect or a re-rendered page. The `app/Api/` namespace from v1 of this plan is removed — there's just `app/Controllers/`.
4. **Auth transport:** pure bearer, no CSRF tokens. Because the app is server-rendered, the bearer token rides in an `HttpOnly; SameSite=Lax; Secure` cookie (a `Bearer` request header from inside a server-rendered POST is impossible without JS — the cookie is the only viable transport). `SameSite=Lax` is the CSRF mitigation; we don't add a separate CSRF token field. See §2 for the threat-model trade-off.
5. **Logout / revocation:** keep a `revoked_tokens` table in the DB. Each issued token includes a `jti` (unique id). Logout inserts the jti into the table. Token verification rejects any jti present in the table. Expired rows are pruned. See §2.6 and §5.

---

## 0. Snapshot of the current state (so the plan makes sense)

```
CharityBridge/
├── api/                              ← endpoints, mixed concerns
│   ├── auth/{login,logout,signup,session,csrf-token}.php
│   ├── campaigns/{index,items}.php
│   ├── contributions.php
│   ├── deposits.php
│   ├── production-offers.php
│   └── purchases.php
├── backend/
│   ├── database/
│   │   ├── charity_bridge.db         ← committed SQLite file (bad)
│   │   ├── init_sqlite.php           ← bootstrap script
│   │   ├── schema.sql                ← MySQL-flavored, not actually used
│   │   └── migrations/00{2..8}_*.php ← six PHP migration scripts
│   ├── includes/{auth,campaign_access,config,db}.php
│   └── sessions/sess_*               ← 16 committed session files (very bad)
└── public/
    ├── *.html                        ← 9 static pages, all inline JS
    ├── router.php                    ← rewrites /api/* → ../api/*.php
    └── assets/{css,js,images}/
```

**How it works today:**

1. `api/auth/login.php` validates email/password, generates `bin2hex(random_bytes(32))`, returns it as `token`. **The token is never stored — it's cosmetic.**
2. `api/auth/login.php` and `api/auth/signup.php` use PHP `$_SESSION` only for a CSRF check.
3. `backend/includes/config.php` calls `session_start()` for every request (writes to `backend/sessions/`).
4. The frontend stores `token` and `user` in `localStorage`. On every API call it sends `X-User-Id: <id>` (no token verification at all).
5. Every non-auth endpoint reads `getallheaders()['X-User-Id']` and trusts it. **Anyone can impersonate anyone by changing one header.**
6. `api/auth/session.php` checks `$_SESSION['user_id']` — but login never sets it, so it always returns `authenticated:false`. The frontend doesn't care because it relies on localStorage.

**This is the central mess that requirement #2 dissolves.**

---

## 1. Restructure to MVC, replace `.html` pages with views, server-render everything

**Goal:** Every page becomes a controller method that renders a view template
file (a `.php` file that emits HTML, with a small `view($name, $data)` helper
and an `e($x)` escape helper — like JSX-for-PHP). Every form post becomes a
POST controller method that processes input and either redirects (Post-Redirect-Get)
or re-renders the page with errors. **No `fetch()`, no JSON endpoints, no JS
state machines.**

### 1.1 Target layout

```
CharityBridge/
├── app/
│   ├── Controllers/                    ← one controller per resource; methods return HTML or redirects
│   │   ├── HomeController.php
│   │   ├── AuthController.php          ← showLogin/login/showSignup/signup/logout
│   │   ├── CampaignsController.php     ← index/show/create/store/edit/update/destroy/my
│   │   ├── CampaignItemsController.php ← store/update/destroy (POST handlers for the create/edit forms)
│   │   ├── ContributionsController.php ← store/cancel
│   │   ├── ProductionOffersController.php ← store/decide
│   │   ├── PurchasesController.php     ← store
│   │   ├── DepositController.php       ← index/store
│   │   └── ProfileController.php
│   ├── Core/
│   │   ├── Router.php                  ← single front-controller dispatcher
│   │   ├── Request.php                 ← wraps $_SERVER, $_GET, $_POST, body
│   │   ├── Response.php                ← html($view,$data), redirect($url), back()
│   │   ├── View.php                    ← view('campaigns/index', [...]) helper
│   │   ├── Auth.php                    ← cookie-based stateless tokens (see §2)
│   │   ├── Db.php                      ← PDO factory for MySQL (reads .env)
│   │   ├── Env.php                     ← parses .env on boot
│   │   ├── Url.php                     ← url('campaigns') (see §4)
│   │   └── Flash.php                   ← one-shot success/error messages across redirects (see §1.6)
│   ├── Models/
│   │   ├── User.php
│   │   ├── Campaign.php
│   │   ├── CampaignItem.php
│   │   ├── Contribution.php
│   │   ├── Deposit.php
│   │   ├── ProductionOffer.php
│   │   ├── Purchase.php
│   │   └── RevokedToken.php
│   └── Views/
│       ├── layouts/
│       │   ├── main.php                ← <html>...<?= $slot ?>...</html>
│       │   └── auth.php                ← centered card layout
│       ├── partials/
│       │   ├── header.php
│       │   ├── footer.php
│       │   ├── nav.php                 ← server-rendered nav (reads current user from request)
│       │   └── flash.php               ← renders flash messages
│       ├── home/index.php
│       ├── auth/{login,signup}.php
│       ├── campaigns/{index,show,create,edit,my}.php
│       ├── profile/index.php
│       └── deposit/index.php
├── routes.php                          ← path → controller@method map
├── public/                             ← web-server docroot (KEPT — see §1.2)
│   ├── index.php                       ← front controller (4-line bootstrap)
│   ├── .htaccess                       ← rewrite all non-asset requests to index.php
│   └── assets/{css,images}/            ← static assets stay here
├── docker/
│   ├── php/Dockerfile
│   └── php/apache-vhost.conf
├── docker-compose.yml
├── database/
│   └── schema.sql                      ← single consolidated MySQL DDL (incl. revoked_tokens)
├── .env.example
└── .env                                ← gitignored
```

### 1.2 What happens to `public/`

Per the user: keep `public/` as the docroot, just stop putting page-HTML there
and stop using `router.php`.

| `public/` item                | Decision   | Why                                                                  |
|-------------------------------|------------|----------------------------------------------------------------------|
| `public/*.html` (all 9)       | **Delete** | Replaced by views in `app/Views/`.                                   |
| `public/router.php`           | **Delete** | Replaced by `public/index.php` front controller.                     |
| `public/assets/css/*`         | **Keep**   | Still needed; views `<link>` to them via the `url()` helper.         |
| `public/assets/images/*`      | **Keep**   | Same.                                                                |
| `public/assets/js/api.js`     | **Delete** | No client-side JSON API in a server-rendered app.                    |
| `public/assets/js/auth.js`    | **Delete** | Login/logout/etc. are now form posts handled by `AuthController`.    |
| `public/assets/js/header-nav.js` | **Delete** | Nav is rendered server-side from the current user.                |
| `public/index.php` (new)      | **Add**    | The front controller.                                                |
| `public/.htaccess` (new)      | **Add**    | Rewrites everything except real files/dirs to `index.php`.           |

If a piece of inline JS in the old HTML pages is doing something that *can't*
be done server-side (e.g. the carousel auto-advance on the home page, the
card-number formatting helpers on the deposit form), keep it as a small
script tag inside the corresponding view. Pure UI behavior is fine — we're
removing the JS that was acting as a client-side controller, not banning JS.

### 1.3 The "JSX-for-PHP" view layer

A view is a PHP file that uses the alternative syntax (`<?php foreach (...): ?>
... <?php endforeach; ?>`) and an `e()` helper for HTML-escaping. No template
engine dependency.

```php
// app/Core/View.php
function view(string $name, array $data = [], ?string $layout = 'main'): string {
    extract($data, EXTR_SKIP);
    ob_start();
    require __DIR__ . "/../Views/$name.php";
    $slot = ob_get_clean();
    if ($layout === null) return $slot;
    ob_start();
    require __DIR__ . "/../Views/layouts/$layout.php";
    return ob_get_clean();
}
function e($x): string { return htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8'); }
function component(string $name, array $data = []): void {
    extract($data, EXTR_SKIP);
    require __DIR__ . "/../Views/partials/$name.php";
}
```

Example view (`app/Views/campaigns/index.php`):

```php
<h1>Browse Campaigns</h1>
<?php if (empty($campaigns)): ?>
    <p>No campaigns yet.</p>
<?php else: ?>
    <div class="grid">
        <?php foreach ($campaigns as $c): ?>
            <article class="campaign-card">
                <h3><?= e($c['title']) ?></h3>
                <p><?= e(mb_strimwidth($c['description'], 0, 120, '…')) ?></p>
                <a href="<?= url('campaigns/' . $c['id']) ?>">View</a>
            </article>
        <?php endforeach ?>
    </div>
<?php endif ?>
```

### 1.4 Routing

`routes.php`:

```php
return [
    ['GET',  '/',                       [HomeController::class,                'index']],
    ['GET',  '/login',                  [AuthController::class,                'showLogin']],
    ['POST', '/login',                  [AuthController::class,                'login']],
    ['GET',  '/signup',                 [AuthController::class,                'showSignup']],
    ['POST', '/signup',                 [AuthController::class,                'signup']],
    ['POST', '/logout',                 [AuthController::class,                'logout']],

    ['GET',  '/campaigns',              [CampaignsController::class,           'index']],
    ['GET',  '/campaigns/create',       [CampaignsController::class,           'create']],
    ['POST', '/campaigns',              [CampaignsController::class,           'store']],
    ['GET',  '/campaigns/{id}',         [CampaignsController::class,           'show']],
    ['GET',  '/campaigns/{id}/edit',    [CampaignsController::class,           'edit']],
    ['POST', '/campaigns/{id}',         [CampaignsController::class,           'update']],
    ['POST', '/campaigns/{id}/delete',  [CampaignsController::class,           'destroy']],
    ['GET',  '/my-campaigns',           [CampaignsController::class,           'my']],

    ['POST', '/campaigns/{id}/items',           [CampaignItemsController::class, 'store']],
    ['POST', '/campaign-items/{id}',            [CampaignItemsController::class, 'update']],
    ['POST', '/campaign-items/{id}/delete',     [CampaignItemsController::class, 'destroy']],

    ['POST', '/campaigns/{id}/contributions',   [ContributionsController::class, 'store']],
    ['POST', '/contributions/{id}/cancel',      [ContributionsController::class, 'cancel']],

    ['POST', '/campaigns/{id}/offers',          [ProductionOffersController::class, 'store']],
    ['POST', '/offers/{id}/decide',             [ProductionOffersController::class, 'decide']],

    ['POST', '/items/{id}/purchase',            [PurchasesController::class,    'store']],

    ['GET',  '/deposit',                [DepositController::class,             'index']],
    ['POST', '/deposit',                [DepositController::class,             'store']],

    ['GET',  '/profile',                [ProfileController::class,             'index']],
];
```

Notes:
- HTML forms can only emit GET and POST. We use POST for everything that
  mutates, with action-flavored URLs (`/campaigns/{id}/delete`,
  `/contributions/{id}/cancel`, `/offers/{id}/decide`) instead of trying to
  fake PUT/DELETE. This is simpler and means we don't need a `_method`
  override hack.
- Every POST that mutates ends with a `Response::redirect(url('...'))` so
  refresh-after-submit doesn't re-submit (Post-Redirect-Get).
- Errors from POST handlers become flash messages plus a redirect back to
  the form, OR a re-render of the form view with an `$errors` array passed in.

### 1.5 The front controller

`public/index.php` (the **only** PHP file in the docroot):

```php
<?php
require __DIR__ . '/../app/bootstrap.php';
(new App\Core\Router(require __DIR__ . '/../routes.php'))->dispatch();
```

`public/.htaccess`:

```
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
```

### 1.6 Flash messages (needed because we redirect after POST)

A tiny one-shot store. Since we have no `$_SESSION`, flash messages can't
ride PHP sessions. Two viable options:

- **Cookie-based flash:** set a short-lived cookie `flash=<json>` in the
  redirect response, read-and-clear in the next request. Works without any
  server-side state.
- **Signed-token flash:** stash the message in a signed cookie (same HMAC
  helper as the auth token). Slightly more code but tamper-resistant if the
  message ever influences logic (it doesn't; it's just text).

Default plan: cookie-based, plain text, one-shot. The flash partial reads
`$_COOKIE['flash']`, displays it, and the response unsets the cookie.

### 1.7 Migration sequence inside step 1

1. Create the skeleton directories and `app/bootstrap.php`.
2. Move DB connection logic from `backend/includes/db.php` → `app/Core/Db.php` (MySQL only).
3. Stub `app/Core/Auth.php`, `Url.php`, `Env.php`, `View.php`, `Router.php`, `Request.php`, `Response.php`, `Flash.php`.
4. Wire up routing — minimal hello-world rendering through a layout.
5. Port one HTML page at a time (start with `index.html` → `home/index.php`), confirm it renders, move to next.
6. Convert each fetch-driven form/modal into a server-rendered form posting to a controller. The biggest per-page lifts:
   - **Campaigns create/edit:** the items grid stays as one form; on submit, controller iterates items in a transaction.
   - **Campaign detail "Support" modal:** becomes a small inline form with three radio-selectable contribution types.
   - **Campaign detail "Offer to produce" modal:** becomes an inline form on the campaign page (or its own `/campaigns/{id}/offer` page; pick whichever is less screen-cluttered after the layout pass).
   - **Item purchase modal:** becomes a per-item POST form with quantity input.
   - **Edit campaign "Accept/Reject offer":** two buttons in a form, each posts to `/offers/{id}/decide` with `action=accept|reject`.
7. Delete the corresponding `public/*.html` and `api/*.php` *only after* the new route renders correctly end-to-end.
8. After every page is migrated, delete `public/router.php`, `public/assets/js/{api,auth,header-nav}.js`, and the entire `api/` and `backend/` trees.

---

## 2. Simplify authentication — stateless signed token in a cookie + revocation table

**Goal:** Pure-bearer, no `$_SESSION`, no `backend/sessions/`, no manual CSRF
tokens, no cosmetic random strings, no client-supplied `X-User-Id` trust.
Login issues an HMAC-signed token; the token rides in an `HttpOnly` cookie;
the server verifies the signature and checks a `revoked_tokens` table; logout
inserts the token's `jti` into that table.

### 2.1 Why the bearer rides in a cookie (not an `Authorization` header)

The user picked **(a) all server-rendered** AND **(b) pure bearer, no CSRF**.
Server-rendered HTML forms can only send what the browser auto-attaches —
they cannot set an `Authorization` header on a `<form action="..." method="POST">`.
That leaves cookies as the only viable transport for the bearer token.

The combination "cookie-borne token, no CSRF token field" is safe **only**
because we set `SameSite=Lax` on the cookie. With `SameSite=Lax`:

- The cookie is **not** sent on cross-site `<form method="POST">` submissions
  (the classic CSRF vector). ✅
- The cookie **is** sent on top-level cross-site GET navigations (so links
  from email/Slack to `/profile` still work for a logged-in user). ✅
- The cookie is sent on same-site requests of any kind, which is what we want.

This delivers what the user asked for ("no CSRF token") without leaving the
classic CSRF hole open. The trade-off vs. an `Authorization: Bearer` header
is real but small, and is the standard answer for server-rendered apps.

### 2.2 Token format (compact JWT-ish)

```
base64url( JSON{"jti":"<16-hex>","uid":42,"iat":1717000000,"exp":1719592000} )
  + "."
  + base64url( HMAC-SHA256(AUTH_SECRET, payload) )
```

- `jti` is a 16-byte random hex (the unique id we revoke against).
- `iat` issued-at (epoch seconds).
- `exp` expiry (epoch seconds), `iat + AUTH_TOKEN_TTL_SECONDS`.
- `uid` user id.

We don't need the JWT "header" field — there's only one algorithm (HS256),
so saving the bytes is fine. If the user later wants a real JWT library,
the format above is trivially compatible.

### 2.3 Cookie attributes

```
Set-Cookie: auth=<token>; HttpOnly; SameSite=Lax; Path=/; Secure; Max-Age=2592000
```

- `HttpOnly` — JS can't read it (XSS can't exfiltrate the token).
- `SameSite=Lax` — CSRF mitigation (see §2.1).
- `Secure` — only sent over HTTPS in production. **In local dev** (`APP_ENV=local`)
  we drop `Secure` so the cookie works over plain HTTP. The cookie helper
  reads `APP_ENV` and adjusts.
- `Path=/` — but if the app runs in a subfolder (see §4), we set
  `Path=<APP_BASE_PREFIX>/` so the cookie scopes to this app and doesn't
  leak across student projects on the same shared host.

### 2.4 `app/Core/Auth.php` API

```php
class Auth {
    public static function issue(int $userId): string;          // generates jti, signs, returns token
    public static function verify(string $token): ?array;        // returns ['uid'=>..., 'jti'=>...] or null
    public static function setCookie(string $token): void;       // emits Set-Cookie with the right attrs
    public static function clearCookie(): void;                  // emits an expired cookie
    public static function currentUserId(Request $r): ?int;      // reads cookie, verifies, checks revocation, returns uid
    public static function requireUser(Request $r): array;       // 302→/login on miss; loads & returns user row
    public static function requireRole(Request $r, string ...$roles): array;  // 403 on wrong role
    public static function revoke(string $jti, int $exp): void;  // INSERT INTO revoked_tokens
}
```

Verification order in `currentUserId()`:
1. Pull token from `$_COOKIE['auth']`.
2. Split, base64-decode, recompute HMAC, constant-time compare.
3. Reject if `exp < now`.
4. SELECT 1 FROM `revoked_tokens` WHERE `jti = ?`. Reject if found.
5. Return `uid`.

### 2.5 Login / signup / logout flow (new)

`POST /login   { email, password }`:
- Validate, look up user, verify password.
- On success: `$token = Auth::issue($user['id']); Auth::setCookie($token); Response::redirect(url('profile'))`.
- On failure: re-render `auth/login.php` with `$errors = ['Invalid email or password.']`.

`POST /signup`:
- Validate, INSERT user, then same login dance (issue + setCookie + redirect).

`POST /logout`:
- Read cookie, decode token, extract `jti` and `exp`.
- `Auth::revoke($jti, $exp)` — insert into `revoked_tokens`.
- `Auth::clearCookie()`.
- `Response::redirect(url(''))`.

No CSRF token field on any of these forms — `SameSite=Lax` handles it.

### 2.6 The `revoked_tokens` table

Added to `database/schema.sql`:

```sql
CREATE TABLE revoked_tokens (
    jti        CHAR(32)    NOT NULL PRIMARY KEY,
    user_id    INT         NOT NULL,
    revoked_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP   NOT NULL,
    INDEX idx_revoked_expires (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- `jti` is the primary key (32 hex chars = 16 bytes).
- `expires_at` lets us prune. Once a row's `expires_at` is in the past, the
  signed token would be rejected for `exp < now` anyway, so the row is
  redundant and can be deleted.
- Pruning is best-effort and runs lazily: at the start of each
  `Auth::revoke()` call, do `DELETE FROM revoked_tokens WHERE expires_at < NOW()`.
  This keeps the table small without needing a cron job. (For high-traffic
  apps we'd run it from a scheduled job; CharityBridge will never have
  enough churn to need that.)

### 2.7 Backend changes (search-and-destroy)

- Every endpoint that does `getallheaders()['X-User-Id']` calls
  `Auth::currentUserId($request)` instead. Hit list:
  `campaigns/index.php`, `campaigns/items.php`, `contributions.php`,
  `production-offers.php`, `purchases.php`, `deposits.php`, `auth/session.php`.
  (All of these are being moved into Controllers in §1 anyway — this is the
  semantic change applied during that move.)
- Every endpoint loses its bespoke `getCurrentUser…` helper.
- `backend/includes/config.php` loses `session_start()`, the `session.cookie_*`
  ini_set calls, and the `mkdir($sessionPath)` block.
- `api/auth/csrf-token.php` is deleted (no CSRF tokens anymore).
- `api/auth/session.php` is replaced by **rendering** of the current user in
  every view via the layout (the layout reads `Auth::currentUserId()` once and
  passes the user row down to partials). There is no longer an endpoint
  whose job is "tell the JS who is logged in" because there is no JS.

### 2.8 Frontend changes

This is mostly a deletion. The three JS files in `public/assets/js/` go
away (per §1.2). The `localStorage.auth_token` and `localStorage.user`
mechanisms are gone — the cookie is the source of truth, and "who am I" is
answered by server-side rendering.

### 2.9 Acceptance check for §2

- `grep -rn '\$_SESSION\|session_start\|session_destroy\|csrf' app/ public/ database/` → zero hits.
- `grep -rn 'X-User-Id\|localStorage' app/ public/` → zero hits.
- `backend/sessions/` directory does not exist.
- A logged-in browser can navigate any protected page; without the cookie,
  protected pages 302 to `/login`.
- Logout: hit `/logout`, then try to load `/profile` *with the same token
  somehow re-attached* (e.g. via a Set-Cookie replay tool) → server rejects
  it because the `jti` is in `revoked_tokens`.
- Cross-site `<form method=POST action=https://app/.../campaigns>` from a
  third-party origin: the auth cookie is **not** sent (SameSite=Lax) → the
  request is unauthenticated → 302 to `/login`.

---

## 3. Create `.env` with every environment variable extracted from the app

**Goal:** Every value that varies by environment lives in `.env`, parsed once
at boot via `app/Core/Env.php`. `.env` is gitignored; `.env.example` is
committed and lists every key with a sensible default.

### 3.1 Inventory of values to extract

Sourced from a full grep of the current code:

| Key                       | Current location                                | Default value                              |
|---------------------------|-------------------------------------------------|--------------------------------------------|
| `APP_ENV`                 | new                                             | `local`                                    |
| `APP_DEBUG`               | `backend/includes/config.php` `display_errors=1`| `true`                                     |
| `APP_NAME`                | `backend/includes/config.php` `SITE_NAME`       | `CharityBridge`                            |
| `APP_BASE_URL`            | `backend/includes/config.php` `SITE_URL`        | `http://localhost:8080`                    |
| `APP_URL_MARKER`          | new (see §4)                                    | `charity-api`                              |
| `DB_HOST`                 | new                                             | `db`                                       |
| `DB_PORT`                 | new                                             | `3306`                                     |
| `DB_NAME`                 | new                                             | `charitybridge`                            |
| `DB_USER`                 | new                                             | `charity`                                  |
| `DB_PASSWORD`             | new                                             | `charity`                                  |
| `DB_CHARSET`              | `backend/includes/config.php`                   | `utf8mb4`                                  |
| `AUTH_SECRET`             | new (§2)                                        | (random 64-char hex, generated by user)    |
| `AUTH_TOKEN_TTL_SECONDS`  | new                                             | `2592000` (30 days)                        |
| `AUTH_COOKIE_NAME`        | new (§2.3)                                      | `auth`                                     |
| `PASSWORD_MIN_LENGTH`     | `backend/includes/config.php`                   | `8`                                        |
| `MIN_DEPOSIT`             | `api/deposits.php`                              | `1.00`                                     |
| `MAX_DEPOSIT`             | `api/deposits.php`                              | `10000.00`                                 |
| `MAX_QUANTITY_PER_PURCHASE` | `api/purchases.php`                           | `100`                                      |

Notes:
- `DB_DRIVER` / `DB_SQLITE_PATH` from v1 of this plan are gone — MySQL only.
- `ALLOWED_ORIGIN` from v1 is gone — there's no cross-origin frontend
  anymore (everything is same-origin server-rendered HTML), so we don't emit
  `Access-Control-Allow-Origin` headers at all. The 11 hardcoded origin
  headers in the existing endpoints get *deleted*, not parameterized.

### 3.2 `.env.example` (committed)

```
APP_ENV=local
APP_DEBUG=true
APP_NAME=CharityBridge
APP_BASE_URL=http://localhost:8080
APP_URL_MARKER=charity-api

DB_HOST=db
DB_PORT=3306
DB_NAME=charitybridge
DB_USER=charity
DB_PASSWORD=charity
DB_CHARSET=utf8mb4

AUTH_SECRET=replace-me-with-64-hex-chars
AUTH_TOKEN_TTL_SECONDS=2592000
AUTH_COOKIE_NAME=auth

PASSWORD_MIN_LENGTH=8

MIN_DEPOSIT=1.00
MAX_DEPOSIT=10000.00
MAX_QUANTITY_PER_PURCHASE=100
```

### 3.3 `app/Core/Env.php`

A 30-line class: parse `KEY=value` and `KEY="value with spaces"`, ignore
`#` comments, set `$_ENV` and `getenv`. Boot calls
`Env::load(__DIR__.'/../../.env')`. Throws if `AUTH_SECRET` is missing or
literally `replace-me-…`.

### 3.4 Wire-up

- Replace every `define('FOO', ...)` in `backend/includes/config.php` with `env('FOO')`.
- Delete every `Access-Control-Allow-Origin` / `Access-Control-Allow-Methods`
  / `Access-Control-Allow-Headers` / `Access-Control-Allow-Credentials` /
  preflight `OPTIONS` block in every endpoint — same-origin server-rendered
  apps don't need them.
- Replace `MIN_DEPOSIT` / `MAX_DEPOSIT` constants in `api/deposits.php` and
  `MAX_QUANTITY_PER_PURCHASE` in `api/purchases.php` with env reads. (These
  files are being moved into Controllers in §1; this is the semantic change
  applied during that move.)

### 3.5 Acceptance check for §3

- `grep -rEn "'http://localhost|http://localhost" app/ public/` → zero hits.
- `grep -rn "define\('" app/` → zero hits.
- `grep -rn 'Access-Control-Allow' app/ public/` → zero hits.
- `.env.example` exists and is committed; `.env` exists locally and is in
  `.gitignore` (it already is).

---

## 4. Subfolder-aware URL handling for "deploy in any subfolder"

**Goal:** When the app is unpacked at any URL whose path *contains* the
literal segment `charity-api`, the app figures out its own prefix by looking
at the request URL, and prepends that prefix to every link / form action /
asset URL / redirect.

### 4.1 The rule (per the user)

> Add a `https…/charity-api/<the subpaths>` and replace only the subpaths.
> We can be sure that everything before the `charity-api` string is the
> subfolder where this is run.

So the deployment URL always contains the literal segment `charity-api`
somewhere in its path. The app knows its own root by finding that segment in
`$_SERVER['REQUEST_URI']` and using everything up to and including it as the
base.

Examples:

| Request URI                                    | Computed `APP_BASE_PREFIX`         | A link to "campaigns" becomes                   |
|------------------------------------------------|------------------------------------|-------------------------------------------------|
| `/charity-api/campaigns`                       | `/charity-api`                     | `/charity-api/campaigns`                        |
| `/students/vstanoyska/charity-api/campaigns`   | `/students/vstanoyska/charity-api` | `/students/vstanoyska/charity-api/campaigns`    |
| `/some/very/deep/sub/charity-api/login`        | `/some/very/deep/sub/charity-api`  | `/some/very/deep/sub/charity-api/login`         |

Because the app is now fully server-rendered (§1), there is **only the
server side** to handle — no browser-side prefix detection is needed. Every
URL the browser sees is already prefixed correctly when the page is rendered.

### 4.2 Server side: `app/Core/Url.php`

```php
class Url {
    public static string $prefix = '';
    public static function init(string $requestUri, string $marker): void {
        $path = parse_url($requestUri, PHP_URL_PATH) ?? '/';
        $idx = strpos($path, "/$marker");
        self::$prefix = $idx === false ? '' : substr($path, 0, $idx + strlen($marker) + 1);
    }
    public static function to(string $path = ''): string {
        return self::$prefix . '/' . ltrim($path, '/');
    }
}
function url(string $p = ''): string { return Url::to($p); }
function asset(string $p): string { return Url::to('assets/' . ltrim($p, '/')); }
```

`app/bootstrap.php` calls `Url::init($_SERVER['REQUEST_URI'], env('APP_URL_MARKER'))` exactly once, before the Router dispatches.

### 4.3 Where the prefix gets used

- **Links in views:** `<a href="<?= url('campaigns') ?>">`
- **Form actions:** `<form method="POST" action="<?= url('login') ?>">`
- **Asset URLs:** `<link rel="stylesheet" href="<?= asset('css/style.css') ?>">` and `<img src="<?= asset('images/hero.jpg') ?>">`
- **Redirects from controllers:** `Response::redirect(url('profile'))`
- **Auth cookie `Path` attribute:** `Auth::setCookie()` uses `Url::$prefix . '/'` (or `/` if prefix is empty), so the cookie is scoped to this app.

### 4.4 Routing implications

The Router strips `Url::$prefix` from the request path before matching:

```php
$path = substr($requestPath, strlen(Url::$prefix)) ?: '/';
```

This means routes are written as `/campaigns`, `/login`, etc. — the prefix
is invisible to `routes.php`.

### 4.5 The "before charity-api" guarantee

The user is explicit: **everything before `charity-api` is part of the
deployment subfolder, nothing inside `charity-api/...` is touched**. We
neither parse nor rewrite the suffix — just compute the prefix once and
prepend it.

### 4.6 Acceptance check for §4

- Place the project at `http://localhost:8080/charity-api/`. Browse
  `/charity-api/campaigns` → renders. Every `<a href>`, `<form action>`,
  `<link>`, `<img>` source, and post-redirect `Location` header includes
  the `/charity-api` prefix.
- Move the same project to `http://localhost:8080/foo/bar/charity-api/`
  without changing any code. Every URL emitted by the server now begins
  with `/foo/bar/charity-api`. Login, navigation, asset loading, and
  cookie scoping all still work.
- Auth cookie inspector: the `auth` cookie's `Path` attribute equals
  `APP_BASE_PREFIX/` (so it doesn't leak across other apps on the same host).

---

## 5. Consolidate migrations into one MySQL `.sql` file + Docker init + local CLI command

**Goal:** Replace the seven PHP scripts (`init_sqlite.php` + six numbered
migrations) and the unused MySQL-flavored `schema.sql` with **one** SQL file,
runnable two ways:

- Docker Compose mounts it into the MySQL image's `/docker-entrypoint-initdb.d/`
  so the schema is applied automatically on first container start.
- Local dev runs one CLI command to apply it to a running MySQL.

### 5.1 The single file: `database/schema.sql`

Reconstructed by reading the existing migrations end-to-end and converting
back to MySQL syntax (the existing migrations are written for SQLite). Final
schema includes:

- `users` (with `virtual_balance` from migration 004 already merged in)
- `campaigns`
- `campaign_items` (with `producer_id` from migration 005 already merged in)
- `production_offers`
- `contributions`
- `purchases`
- `deposits`
- **`revoked_tokens`** (new, per §2.6 — token revocation list)

Tables that exist in the old `schema.sql` but **are not used by any code** and
are therefore dropped:

- `password_reset_tokens` — never referenced anywhere; password reset isn't
  implemented. If reset is added later, that's a separate migration.
- `sessions` — we no longer use server-side sessions (§2).

The file uses the engine + charset combo the original schema had:
`ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`. All
foreign keys are explicit. All `id` columns are `INT AUTO_INCREMENT`.

The file ends with optional seed `INSERT` statements behind `-- SEED:`
comment markers for demo users / a starter campaign, plus a documented
`mysql … < schema.sql` invocation at the very bottom (so the local-dev
command lives next to the SQL it runs).

### 5.2 Docker Compose wiring

`docker-compose.yml` mounts `./database/schema.sql` into the MySQL service's
`/docker-entrypoint-initdb.d/01-schema.sql`. MySQL applies it the first time
the data volume is empty. Subsequent `docker compose up` runs are no-ops on
the schema (existing data is preserved). To force a re-apply, the user runs
`docker compose down -v` to drop the volume.

### 5.3 Local CLI command (no Docker)

In the README's "Run locally without Docker" section, the user runs:

```sh
mysql -h 127.0.0.1 -P 3306 -u charity -pcharity charitybridge < database/schema.sql
```

If they don't have a `charitybridge` database yet:

```sh
mysql -h 127.0.0.1 -P 3306 -u root -p \
    -e "CREATE DATABASE IF NOT EXISTS charitybridge CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
        CREATE USER IF NOT EXISTS 'charity'@'%' IDENTIFIED BY 'charity';
        GRANT ALL ON charitybridge.* TO 'charity'@'%'; FLUSH PRIVILEGES;"
mysql -h 127.0.0.1 -P 3306 -u charity -pcharity charitybridge < database/schema.sql
```

Document both. The bottom of `database/schema.sql` includes these as a
comment block.

### 5.4 What gets deleted

- `backend/database/migrations/002_add_campaigns.php`
- `backend/database/migrations/003_add_campaign_items.php`
- `backend/database/migrations/004_add_purchases.php`
- `backend/database/migrations/005_add_production_offers.php`
- `backend/database/migrations/006_add_contributions.php`
- `backend/database/migrations/008_add_deposits.php`
  (`007_add_campaign_invites.php` is already deleted in the working tree.)
- `backend/database/init_sqlite.php`
- `backend/database/charity_bridge.db`
- `backend/database/schema.sql` (replaced by `database/schema.sql` at the new top-level location)

### 5.5 Acceptance check for §5

- `database/schema.sql` exists, is the only DDL file in the repo, creates
  every table the app uses (including `revoked_tokens`), and runs cleanly
  against an empty MySQL 8.x database.
- `docker compose up -d` starts MySQL with the schema applied, no manual steps.
- `docker compose down -v && docker compose up -d` re-applies the schema on
  the empty volume (proving the bootstrap works).
- `mysql … < database/schema.sql` succeeds against an empty database for local dev.
- `grep -rin sqlite app/ public/ database/ docker/ docker-compose.yml` returns zero hits.

---

## 6. Docker for backend + DB + compose with schema bootstrapping

**Goal:** `docker compose up` produces a working stack: PHP-FPM (or Apache)
serving the app, MySQL running with the schema applied, both reachable at
`http://localhost:8080/charity-api/`.

### 6.1 `docker/php/Dockerfile`

Base on the official `php:8.2-apache` image (simplest — Apache + mod_php +
mod_rewrite all in one). Steps:

```dockerfile
FROM php:8.2-apache
RUN docker-php-ext-install pdo pdo_mysql && a2enmod rewrite
COPY docker/php/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY . /var/www/html
WORKDIR /var/www/html
RUN chown -R www-data:www-data /var/www/html
EXPOSE 80
```

The Apache vhost sets `DocumentRoot /var/www/html/public` and
`<Directory>` allows `AllowOverride All`. We add a `public/.htaccess` that
rewrites every non-asset request to `index.php`.

### 6.2 `docker/mysql/Dockerfile` (optional)

Usually unnecessary — the official `mysql:8` image is sufficient. We only
need a custom Dockerfile if we want to bake in a custom `my.cnf`. **Default
plan: skip the custom Dockerfile and use `image: mysql:8` directly in
compose.** If the user *insists* on a Dockerfile per service, we make a
1-line `FROM mysql:8` image with `COPY ./docker/mysql/my.cnf
/etc/mysql/conf.d/`.

### 6.3 `docker-compose.yml`

```yaml
services:
  app:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
    ports:
      - "8080:80"
    env_file: .env
    depends_on:
      db:
        condition: service_healthy
    volumes:
      - .:/var/www/html
  db:
    image: mysql:8
    environment:
      MYSQL_DATABASE: ${DB_NAME}
      MYSQL_USER: ${DB_USER}
      MYSQL_PASSWORD: ${DB_PASSWORD}
      MYSQL_ROOT_PASSWORD: ${DB_PASSWORD}root
    volumes:
      - dbdata:/var/lib/mysql
      - ./database/schema.sql:/docker-entrypoint-initdb.d/01-schema.sql:ro
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 5s
      timeout: 3s
      retries: 20
    ports:
      - "3306:3306"
volumes:
  dbdata:
```

### 6.4 `.htaccess` in `public/`

```
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
```

### 6.5 The user's chosen URL

After step 4 we deploy at `http://localhost:8080/` for development AND at
`http://server/.../charity-api/` for production — both work because of the
prefix-detection logic. Inside the container we still serve from
`/var/www/html/public/`; the prefix is determined per-request from the
request URL.

### 6.6 README run instructions

```sh
cp .env.example .env
# generate AUTH_SECRET:
php -r "echo bin2hex(random_bytes(32));"
# paste into .env

docker compose up -d
# app:  http://localhost:8080
# db:   localhost:3306
docker compose logs -f app
```

Local dev without Docker:

```sh
cp .env.example .env
# (assume MySQL running, schema already applied — see step 5.3)
php -S localhost:8080 -t public public/index.php
```

### 6.7 Acceptance check for step 6

- Fresh checkout + `docker compose up -d` brings up a working stack with
  schema applied.
- `docker compose down -v && docker compose up -d` re-applies the schema on
  the empty volume (proving the bootstrap works).
- The README has both run paths documented and copy-pasteable.

---

## 7. Remove every useless file

**Goal:** Strip everything not used by the new MVC + simplified-auth +
single-SQL world. The `public/` directory **stays** (per the user's
clarification), only the obsoleted contents inside it are removed. Each
deletion is justified.

| Path                                                    | Why                                              |
|---------------------------------------------------------|--------------------------------------------------|
| `backend/sessions/sess_*` (all 16)                      | No more PHP sessions (§2). Plus they should never have been committed. |
| `backend/sessions/`                                     | Empty directory after the above.                 |
| `backend/database/charity_bridge.db`                    | Committed SQLite file. We're on MySQL now; even if we kept SQLite, no DB file should be in git. |
| `backend/database/init_sqlite.php`                      | Replaced by `database/schema.sql` (§5).          |
| `backend/database/migrations/00{2..6,8}_*.php`          | Replaced by `database/schema.sql` (§5).          |
| `backend/database/migrations/`                          | Empty directory after the above.                 |
| `backend/database/schema.sql`                           | Old MySQL DDL — replaced by `database/schema.sql` at the new top-level location (§5). |
| `backend/database/`                                     | Empty after the above; remove.                   |
| `backend/includes/auth.php`                             | Re-homed into `app/Core/Auth.php` and rewritten (§2). |
| `backend/includes/campaign_access.php`                  | Re-homed into `app/Models/Campaign.php` (or as a small policy class). |
| `backend/includes/config.php`                           | Re-homed into `app/Core/Env.php` + `app/bootstrap.php` (§3). |
| `backend/includes/db.php`                               | Re-homed into `app/Core/Db.php` (§1). |
| `backend/includes/`                                     | Empty after the above; remove.                   |
| `backend/`                                              | Empty after the above; remove the whole tree.    |
| `api/auth/csrf-token.php`                               | No CSRF in cookie-with-SameSite world (§2).      |
| `api/auth/session.php`                                  | Replaced by server-side rendering of "current user" in the layout (§2.7). |
| `api/auth/{login,logout,signup}.php`                    | Re-homed into `AuthController` (§1). |
| `api/campaigns/{index,items}.php`                       | Re-homed into `CampaignsController` / `CampaignItemsController`. |
| `api/{contributions,deposits,production-offers,purchases}.php` | Re-homed into the corresponding Controllers. |
| `api/`                                                  | Empty after the above; remove.                   |
| `public/router.php`                                     | Replaced by `public/index.php` front controller (§1). |
| `public/*.html` (all 9)                                 | Replaced by views (§1). |
| `public/assets/js/api.js`                               | No client-side JSON API (§1.2). |
| `public/assets/js/auth.js`                              | Login/logout are server-rendered form posts now. |
| `public/assets/js/header-nav.js`                        | Nav is server-rendered. |
| `public/assets/js/`                                     | If empty after the above three deletions, remove. If any per-page UI snippet was extracted from inline scripts (e.g. carousel auto-advance), keep that one and the directory. |
| `composer.lock`                                         | Already gitignored. If it exists locally, delete; we don't ship one until/unless we add Composer (we don't need it — no external deps). |

### 7.1 What survives in `public/`

After cleanup, `public/` contains exactly:

- `index.php` (new, front controller)
- `.htaccess` (new, rewrites)
- `assets/css/` (kept — every existing stylesheet is still wired into a view)
- `assets/images/` (kept — used by views)
- `assets/js/` (only kept if there's a residual UI snippet to host; otherwise removed)

### 7.2 Acceptance check for §7

- `find . -name ".DS_Store" -o -name "*.bak" -o -name "*.swp" -o -name "*.log"` returns nothing.
- No `backend/` directory exists.
- No `api/` directory exists.
- `public/` contains the items listed in §7.1 and nothing else.
- `git ls-files | grep sessions` returns nothing.
- `git ls-files | grep '\.db$'` returns nothing.
- `grep -rn 'localStorage\|fetch(\|XMLHttpRequest' public/` returns at most a tiny handful of UI-only matches (carousel, card-number formatter), zero matches related to auth or API calls.

---

## 8. Delete comments

**Goal:** Apply the project rule: *no comments unless the WHY is non-obvious*.
Sweep the codebase removing decorative, obvious, or stale comments.

### 8.1 Categories to delete

- All `/** … */` PHPDoc blocks that say what a function does when the name
  already says it (e.g. `/** Handle login form submission */ function handleLogin(...)`).
- Section banners like `// Phase 1: Foundation & Authentication`,
  `// Database configuration`, `// Application constants`,
  `// Password requirements`, `// Session configuration`,
  `// Error reporting (disable in production)`, `// Start session if not already started`,
  `// Set JSON header`, `// Handle preflight OPTIONS request`,
  `// IMPORTANT: Allow credentials` (appears literally twice in the codebase),
  `// Changed from MySQL to SQLite`, `// Changed from Strict to Lax for redirects`,
  `// Uncomment for HTTPS`, `// Will be populated by JavaScript`,
  `// Auto-advance carousel every 5 seconds`,
  `// Try to parse as JSON`, etc.
- HTML comments like `<!-- Will be populated by JavaScript -->`,
  `<!-- Image Carousel -->`, `<!-- Filters -->`, `<!-- Browse All Campaigns -->`.
- The few JS docblocks that survive into any UI-only snippet kept after §7
  (e.g. `/** GET request */`, `/** POST request */`).

### 8.2 Categories to keep

- Comments that flag a *non-obvious* invariant:
  - `// quantity_available = -1 means unlimited` (semantically loaded, not deducible from the column name)
  - The `-- SEED:` markers at the bottom of `database/schema.sql` (act as documented opt-in seed data)
- The `database/schema.sql` "how to run this locally" comment block (it lives at end-of-file and is the canonical run instruction).

### 8.3 Sweep method

After all the structural moves are done (§§1–7), run a final pass over
each file in `app/`, `public/index.php`, `public/.htaccess`, every view in
`app/Views/`, every Dockerfile, every `.yml`, and `database/schema.sql`.
Delete anything that fails the WHY-test.

### 8.4 Acceptance check for §8

- `grep -rn "^\s*//\|^\s*/\*\|^\s*\*\|<!--" app/ public/ database/ docker/ docker-compose.yml` shows only the small set of intentional kept comments.
- A diff-reviewer skim of one randomly chosen view and one controller finds zero "what does this code do" comments.

---

## Order of execution & sequencing notes

The eight requirements have dependencies:

```
  ┌─ 3 (env) ─┐
  │           │
  1 (MVC) ──► 2 (auth) ──► 4 (URL prefix) ──► 6 (docker) ──► 7 (cleanup) ──► 8 (de-comment)
  │                                              ▲
  └─ 5 (single SQL) ─────────────────────────────┘
```

Suggested working order:

1. **§3 first** (env vars) — small, isolated, makes everything afterwards configurable.
2. **§5** (consolidate to one `.sql`) — independent of the MVC refactor; lets us validate the schema.
3. **§1** (MVC restructure) — the biggest mechanical change. Do it before touching auth so we have stable Controllers to mutate. The form-conversion (§1.7 step 6) is the bulk of the work.
4. **§2** (auth simplification) — easier inside the MVC structure.
5. **§4** (URL prefix) — one helper, a few view changes. Best done after §1 because it lives in `app/Core/Url.php` and is consumed by every view.
6. **§6** (Docker) — needs §3, §5, and a working app to copy in.
7. **§7** (delete useless files) — only safe after the new structure is verified end-to-end.
8. **§8** (comment sweep) — final pass.

Each step ends with the acceptance checks listed under it. The repo should
be committable (and ideally bootable) at the end of every step.

---

## Resolved questions (these were open in v1 of this plan; the user has answered them)

1. **DB engine:** **MySQL.** SQLite is dropped from the plan entirely.
2. **`public/` directory:** **Kept** as the web-server docroot. Only obsoleted contents are removed (the `*.html` pages, `router.php`, the three page-glue JS files). `assets/css/` and `assets/images/` stay; `index.php` and `.htaccess` are added.
3. **Server-rendered or hybrid:** **Fully server-rendered.** No JSON API at all. Every form is a traditional `<form method="POST">`; every mutating action redirects with PRG; flash messages cover the cross-redirect feedback.
4. **CSRF:** **No CSRF token field.** The auth cookie uses `SameSite=Lax` (browser-enforced same-site protection) which mitigates the standard CSRF vector for our form set. See §2.1.
5. **Logout / revocation:** **DB-backed revocation list.** New `revoked_tokens` table; logout inserts the token's `jti`; verification rejects revoked tokens; expired rows are pruned lazily on each new revocation. See §2.6.

