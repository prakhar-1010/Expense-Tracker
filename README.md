# Expense Tracker

A simple PHP expense tracking web app that helps users record daily spending, set monthly budgets, and review reports without needing a database server. The application stores data in XML using PHP's `SimpleXML`, making it lightweight and easy to run locally with XAMPP or the PHP built-in server.

## Features

- User registration and login with hashed passwords
- Personal dashboard with monthly spending summary
- Add, view, search, sort, and delete expenses
- Monthly budget tracking with remaining balance and usage progress
- Category-wise budget planning and spending status
- Monthly recurring expenses that auto-apply for the current month
- CSV import for bulk expense uploads
- CSV export for monthly reports
- Category summaries, recent transactions, and spending trends
- Clean Bootstrap-based UI with responsive layouts

## Tech Stack

- PHP
- SimpleXML for file-based storage
- XML + DTD for application data structure
- Bootstrap 5
- Vanilla JavaScript

## Project Structure

```text
expense-tracker/
|-- add_expense.php
|-- budget.php
|-- config.php
|-- data.xml
|-- delete.php
|-- dtd.dtd
|-- footer.php
|-- index.php
|-- login.php
|-- logout.php
|-- navbar.php
|-- README.md
|-- register.php
|-- report.php
|-- script.js
|-- style.css
|-- view_expense.php
```

## How It Works

- `config.php` contains the core app logic for authentication, validation, XML persistence, budgets, expenses, recurring expenses, and dashboard/report calculations.
- `data.xml` stores users, expenses, budgets, recurring expenses, and category budgets.
- Each main page is a standalone PHP route that handles form submission and renders the UI.

## Getting Started

### Prerequisites

- PHP with `SimpleXML` enabled
- A local web server such as XAMPP Apache, or the PHP built-in server
- Write permission for `data.xml`

### Run with XAMPP

1. Clone or download this repository.
2. Place the project inside your XAMPP `htdocs` folder:

```text
C:\xampp\htdocs\expense-tracker
```

3. Start Apache from the XAMPP Control Panel.
4. Open the app in your browser:

```text
http://localhost/expense-tracker/login.php
```

### Run with PHP Built-in Server

Use the PHP executable bundled with XAMPP:

```powershell
C:\xampp\php\php.exe -S localhost:8000 -t C:\xampp\htdocs\expense-tracker
```

Then open:

```text
http://localhost:8000/login.php
```

## Usage

1. Register a new account.
2. Log in to access the dashboard.
3. Set a monthly budget from the Budget page.
4. Add expenses manually or import them from CSV.
5. Create recurring monthly expenses for repeated spending.
6. Review expenses with search, filters, and sorting.
7. Open the Reports page to analyze spending and export CSV data.

## CSV Import Format

The app supports CSV import from the Add Expense page. The expected columns are:

```csv
title,amount,category,date,description
Groceries,1200.00,Food,2026-04-01,Weekly shopping
Bus Pass,850.00,Transport,2026-04-01,Monthly pass
```

## Data Storage

This project does not use MySQL or any external database. All application data is stored in `data.xml`, including:

- users
- expenses
- budgets
- recurring expenses
- category budgets

IDs are generated automatically with prefixes such as `u1`, `e1`, `b1`, `r1`, and `cb1`.

## Validation and Security Notes

- Passwords are stored using PHP password hashing
- Authenticated pages require an active login session
- User input is sanitized and escaped before output
- Amounts and dates are validated before saving

## Linting

Lint a single PHP file:

```powershell
C:\xampp\php\php.exe -l C:\xampp\htdocs\expense-tracker\config.php
```

Lint all PHP files in the project:

```powershell
Get-ChildItem -Path "C:\xampp\htdocs\expense-tracker" -Filter *.php | ForEach-Object { & "C:\xampp\php\php.exe" -l $_.FullName }
```

## Testing

There is no automated test suite configured yet. The current verification approach is:

- lint changed PHP files
- manually test flows like registration, login, adding expenses, setting budgets, importing CSV, and generating reports

## Future Improvements

- edit existing expenses
- stronger reporting and charts
- better recurring expense controls
- automated tests
- database-backed storage option

## License

This project is open for personal learning and portfolio use. Add a license file if you plan to publish it with a specific license.
