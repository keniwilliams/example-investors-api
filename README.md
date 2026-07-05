# Example Investors API

[![CI](https://github.com/keniwilliams/example-investors-api/actions/workflows/ci.yml/badge.svg)](https://github.com/keniwilliams/example-investors-api/actions/workflows/ci.yml)
![PHP](https://img.shields.io/badge/PHP-8.4%2B-777bb4?logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-13.x-ff2d20?logo=laravel&logoColor=white)
![Tests](https://img.shields.io/badge/Tests-PHPUnit-6f42c1)
![Database](https://img.shields.io/badge/Database-MySQL-4479a1?logo=mysql&logoColor=white)

A Laravel API for importing investor CSV data, storing investors and dated investments, and exposing aggregate JSON endpoints.

This project was built as a focused backend/API exercise. It intentionally keeps the implementation close to Laravel conventions: migrations, Eloquent models, form requests, services, API resources, controllers, routes, and tests.

## Requirements covered

- Upload investor CSV data through an API endpoint.
- Store unique investors and dated investment records in MySQL.
- Enforce one investment per investor per investment date.
- Expose JSON aggregate endpoints.
- Expose a paginated investor listing endpoint.
- Avoid unbounded investor listing responses.
- Keep money handling explicit and avoid float storage.
- Include tests around import, money parsing, aggregates, and API responses.

## CSV format

Required headers:

```csv
investor_id,name,age,investment_amount,investment_date
```

Example:

```csv
INV-001,Ada Lovelace,37,1250.50,2026-07-03
INV-002,Grace Hopper,85,"1,250.50",2026-07-04
INV-003,Katherine Johnson,101,3000,2026-07-05
```

### Field rules

| Field | Rule |
| --- | --- |
| `investor_id` | Required source identifier. Stored as `investors.external_id`. |
| `name` | Required non-empty string. |
| `age` | Required integer greater than or equal to `0`. |
| `investment_amount` | Required monetary text. Supports `xxxx`, `xxxx.x`, `xxxx.xx`, and valid quoted thousands separators. |
| `investment_date` | Required date. Accepts `YYYY-MM-DD` or `DD-MM-YYYY` and is normalised internally to `YYYY-MM-DD`. |

Invalid rows are skipped and counted in the import summary rather than failing the whole import.

Some supplied CSV exports use `DD-MM-YYYY` investment dates (for example `13-11-2024`). Both `YYYY-MM-DD` and `DD-MM-YYYY` are accepted; slash-separated formats such as `13/11/2024` are rejected.

## Money handling

Investment amounts are parsed from CSV text, normalised to fixed 2-decimal monetary values, then stored as integer minor units in `investments.amount_minor`.

Examples:

| CSV value | Normalised value | Stored `amount_minor` |
| --- | ---: | ---: |
| `1250` | `1250.00` | `125000` |
| `1250.5` | `1250.50` | `125050` |
| `1250.50` | `1250.50` | `125050` |
| `"1,250.50"` | `1250.50` | `125050` |
| `0.09` | `0.09` | `9` |

Money is never stored or returned as a float. API-facing money values are formatted back into fixed 2-decimal strings, for example:

```json
{
  "average_investment_amount": "1250.50"
}
```

### Comma-separated investment amounts

Comma-grouped amounts are supported when they are valid CSV fields.

Because commas are CSV delimiters, values such as `1,250.50` must be quoted in the uploaded CSV:

```csv
INV-001,Ada Lovelace,37,"1,250.50",2026-07-03
```

Unquoted comma-grouped amounts such as:

```csv
INV-001,Ada Lovelace,37,1,250.50,2026-07-03
```

are parsed as too many columns and rejected as malformed rows.

Malformed grouped values such as `12,50`, `1,25,000`, `1,,250`, and `1,250,00` are also rejected.

### Currency note

Currency is not present in the supplied CSV contract. This MVP stores only integer minor units for the provided amount and does not infer GBP, USD, or any other currency.

In a production system, currency should be recorded explicitly, for example with a `currency_code` column using ISO 4217 codes, or as part of a wider account/investment configuration.

## API endpoints

All endpoints return JSON and are mounted under `/api`.

### `POST /api/imports/investors`

Uploads a CSV file using `multipart/form-data`.

Form-data field:

```text
file
```

Example response shape:

```json
{
  "status": "completed",
  "rows_read": 3,
  "investors_upserted": 3,
  "investments_upserted": 3,
  "rows_skipped": 0
}
```

Raw uploaded CSV files are stored on Laravel's private local disk during import and are deleted after processing.

### `GET /api/investors`

Returns unique investors, paginated with `paginate(100)`. Each investor aggregates their investments rather than assuming a single amount per investor.

```json
{
  "data": [
    {
      "investor_id": "INV001",
      "name": "Jane Smith",
      "age": 42,
      "total_invested": "1250.00",
      "investment_count": 3
    }
  ],
  "links": { "...": "..." },
  "meta": { "...": "..." }
}
```

Investors with no investments return:

```json
{
  "total_invested": "0.00",
  "investment_count": 0
}
```

### `GET /api/investors/average-age`

```json
{
  "average_age": 47.04
}
```

Average age is rounded to 2 decimal places. An empty database returns `0`.

### `GET /api/investments/average-amount`

```json
{
  "average_investment_amount": "517396.36"
}
```

Average investment amount is calculated from integer minor units and formatted as a fixed 2-decimal string. An empty database returns:

```json
{
  "average_investment_amount": "0.00"
}
```

### `GET /api/investments/count`

```json
{
  "total_investments": 10000
}
```

An empty database returns:

```json
{
  "total_investments": 0
}
```

## Postman

Postman exports are included in the repository:

```text
postman/example-investors-api.postman_collection.json
postman/example-investors-api.local.postman_environment.json
```

Import both files into Postman, then select the `Example Investors API - Local` environment.

The local environment uses:

```text
http://127.0.0.1:8101
```

For the CSV upload request, select a local CSV file in the form-data field named `file` before sending.

## Local setup

Install dependencies:

```bash
composer install
```

Copy the environment file:

```bash
cp .env.example .env
php artisan key:generate
```

Configure MySQL in `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=investors_api
DB_USERNAME=investors_api
DB_PASSWORD=investors_api
```

Run migrations:

```bash
php artisan migrate
```

Start the local API server:

```bash
php artisan serve --host=127.0.0.1 --port=8101
```

## Tests

Run the full test suite:

```bash
php artisan test
```

Useful focused test filters:

```bash
php artisan test --filter=InvestorCsvImportServiceTest
php artisan test --filter=InvestorCsvRowDTOTest
php artisan test --filter=InvestorAggregateServiceTest
php artisan test --filter=InvestorApiTest
php artisan test --filter=MoneyFormatterTest
```

## Architecture notes

- Controllers stay thin and return JSON/resources.
- Import parsing is handled by a dedicated import service and row DTO.
- Money parsing is handled at the CSV boundary and stored as integer minor units.
- Money formatting happens only at the API/resource output boundary.
- Investor listing uses aggregate relationship data for investment counts and total invested values.
- Authentication and authorization are intentionally out of scope for this MVP, but routes/controllers are structured so middleware can be added later.

## Scalability notes

The MVP import reads CSV rows incrementally rather than loading the full file into memory. Rows are processed in chunks for database writes.

For larger production imports, the next step would be to store the uploaded CSV privately, create an import record, dispatch a queued job, return `202 Accepted`, and expose an import status endpoint.

That production queue/status flow is intentionally not included in this 90-minute MVP scope.
