# AGENTS.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Repository overview
- This is a small PHP web app (no framework) for expense tracking.
- Runtime/data storage is file-based XML (`data.xml`) accessed through `SimpleXML`.
- Most pages are single-file PHP controllers + views (request handling and HTML rendering in the same file).

## Common commands
- Run via XAMPP Apache:
  - Ensure Apache is running in XAMPP.
  - Open `http://localhost/expense-tracker/login.php`.
- Run with PHP built-in server (without Apache):
  - `C:\xampp\php\php.exe -S localhost:8000 -t C:\xampp\htdocs\expense-tracker`
  - Then open `http://localhost:8000/login.php`.

### Lint / syntax checks
- Lint a single PHP file:
  - `C:\xampp\php\php.exe -l C:\xampp\htdocs\expense-tracker\config.php`
- Lint all PHP files in the repo (PowerShell):
  - `Get-ChildItem -Path "C:\xampp\htdocs\expense-tracker" -Filter *.php | ForEach-Object { & "C:\xampp\php\php.exe" -l $_.FullName }`

### Tests
- There is currently no automated unit/integration test framework configured (no `phpunit.xml`, Composer scripts, or npm test setup).
- Single-test equivalent for targeted verification is:
  - lint the specific file being changed, then
  - manually exercise the relevant page flow in browser (for example: login/register, add expense, filter/delete expense, set budget).

## Architecture and code structure
### Core application layer
- `config.php` is the central service/utilities module and is required by all route pages.
- It handles:
  - session/auth helpers (`startSession`, `requireLogin`, `isLoggedIn`, current user getters),
  - input/value validation and escaping,
  - XML persistence (`loadXML`, `saveXML`, ID generation),
  - business operations for users, expenses, budgets, and dashboard aggregations.
- Most behavior changes should be made in `config.php` first, then reflected in page UIs.

### Route/page files
- Public/auth routes: `login.php`, `register.php`, `logout.php`.
- Authenticated app routes: `index.php` (dashboard), `add_expense.php`, `view_expense.php`, `budget.php`, `delete.php`.
- Route behavior is mostly POST/GET handling at the top of each file plus server-rendered Bootstrap UI below.
- `delete.php` and `view_expense.php?delete=...` both support deletion paths; keep behavior consistent if editing delete flow.

### Shared UI assets
- Shared layout wrapper for most authenticated pages:
  - `navbar.php` opens `<html><body>` + sidebar + `<main>`.
  - `footer.php` closes `<main>` and document tags.
- `index.php` currently renders a separate top navbar layout and does not include `navbar.php`/`footer.php`.
- Frontend behavior is minimal and centralized in `script.js` (password visibility toggle, register password-match check).
- Global styling is in `style.css` with strong Bootstrap variable overrides (dark theme).

### Data model and persistence contract
- Persistent data is in `data.xml` with top-level sections:
  - `users/user`
  - `expenses/expense`
  - `budgets/budget`
- `dtd.dtd` documents the intended XML schema, but element naming in live app data differs from some DTD names (`createdat`/`userid` in app vs `created_at`/`user_id` in DTD comments/declarations). Avoid blindly “fixing” one side without aligning read/write code in `config.php`.
- IDs are generated with prefixes (`u`, `e`, `b`) through `generateNextId`.

## Known implementation notes
- `index.php` links to `report.php`, but `report.php` is not present in this repository.
- `view_expense.php` “Add Expense” button points to `addexpense.php`; actual file is `add_expense.php`.
