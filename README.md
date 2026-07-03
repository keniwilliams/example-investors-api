<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Investor Import Notes

Investment amounts are parsed from CSV decimal text, with optional valid thousands separators, and stored as integer minor units in `investments.amount_minor`.

For example, CSV investment_amount values `1250.50` and quoted `"1,250.50"` both become `125050`. This avoids float precision issues and keeps money handling explicit. API/resource formatting can convert minor units back into fixed 2-decimal display strings later.

Currency is not present in the supplied CSV contract. In a production system, the investment currency should be recorded explicitly, either as a currency column such as `currency_code` using ISO 4217 codes, or as part of a wider investment/account configuration. This MVP stores only integer minor units for the provided amount and does not infer currency.

### Comma-separated investment amounts

Comma-grouped investment amounts are supported when they are valid CSV fields.

Because commas are CSV delimiters, values such as `1,250.50` must be quoted in the uploaded CSV:

```csv
INV-001,Ada Lovelace,37,"1,250.50",2026-07-03
```

Unquoted comma-grouped amounts such as:

```csv
INV-001,Ada Lovelace,37,1,250.50,2026-07-03
```

will be parsed as multiple columns and rejected as malformed rows.

Money-facing API output should convert integer minor units back into fixed 2-decimal strings. For example, `125000` becomes `"1250.00"`, `125050` becomes `"1250.50"`, `90` becomes `"0.90"`, and `9` becomes `"0.09"`. API responses should not expose money as floats.

## API Endpoints

All endpoints return JSON and are mounted under `/api`.

### `GET /api/investors`

Returns unique investors, paginated (`paginate(100)`). Each investor aggregates their investments rather than assuming a single amount per investor.

```json
{
  "data": [
    {
      "investor_id": "INV001",
      "name": "Jane Smith",
      "age": 42,
      "total_invested": "125000.00",
      "investment_count": 3
    }
  ],
  "links": { "...": "..." },
  "meta": { "...": "..." }
}
```

Investors with no investments return `"total_invested": "0.00"` and `"investment_count": 0`.

### `GET /api/investors/average-age`

```json
{
  "average_age": 47.04
}
```

Rounded to 2 decimal places. An empty database returns `{"average_age": 0}`.

### `GET /api/investments/average-amount`

Money is stored internally as integer minor units and formatted as a fixed 2-decimal string for API output, never a float.

```json
{
  "average_investment_amount": "517396.36"
}
```

An empty database returns `{"average_investment_amount": "0.00"}`.

### `GET /api/investments/count`

```json
{
  "total_investments": 10000
}
```

An empty database returns `{"total_investments": 0}`.

### Architecture notes

- Controllers stay thin: they call `InvestorAggregateService` or an Eloquent query and return a resource/JSON response.
- `InvestorResource` shapes the investor list response, including `MoneyFormatter::formatMinorAmount()` for `total_invested`.
- The investor listing uses `withCount('investments')` and `withSum('investments', 'amount_minor')` to avoid N+1 queries.
- Authentication/authorization is intentionally out of scope for this MVP, but routes/controllers are structured so middleware (e.g. Sanctum) can be added later without reworking controller or service responsibilities.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
