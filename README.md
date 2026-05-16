<p align="center">
  <img src="art/queryguard-logo.png" alt="QueryGuard" width="300px"/>
</p>

<p align="center">
  <strong>Zero-config CI gate for Laravel query regressions.</strong><br/>
  Baseline query counts per test, surface N+1 patterns, and fail builds when tests drift — without manual per-test assertions.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-9%20%7C%2010%20%7C%2011%20%7C%2012%20%7C%2013-red?style=flat-square&logo=laravel" alt="Laravel"/>
  <img src="https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=flat-square&logo=php" alt="PHP"/>
  <img src="https://img.shields.io/badge/license-MIT-green?style=flat-square" alt="License"/>
  <img src="https://img.shields.io/badge/Sponsor-GitHub-EA4AAA?style=flat-square&logo=github-sponsors" alt="Sponsor"/>
  <br/><br/>
  <a href="https://www.buymeacoffee.com/MrMarchOne"><img src="https://img.shields.io/badge/Buy%20Me%20a%20Coffee-MrMarchOne-FFDD00?style=flat-square&logo=buy-me-a-coffee&logoColor=black" alt="Buy Me a Coffee"/></a>
</p>

# QueryGuard

> **Zero-config CI gate for Laravel.** Auto-instruments your test suite, baselines query counts per test, and fails PRs that introduce N+1s or query regressions — without you adding a single assertion.

Existing Laravel query tools either run only in dev mode (`beyondcode/laravel-query-detector`, Debugbar) or require devs to opt-in per test with manual assertions (`mattiasgeniar/phpunit-query-count-assertions`). Neither catches regressions automatically in CI on an existing suite.

QueryGuard does. Install, baseline once, and from then on every PR that pushes a test's query count past its baseline (or introduces a new N+1 pattern) fails the build with a precise diff.

## Install

```bash
composer require --dev laramint/queryguard
```

Register the PHPUnit extension in `phpunit.xml`:

```xml
<extensions>
    <bootstrap class="QueryGuard\PHPUnit\QueryGuardExtension"/>
</extensions>
```

## Usage

```bash
# Record the baseline (do this once, commit the file).
php artisan queryguard:baseline

# In CI, this exits non-zero on regression:
php artisan queryguard:check

# Or run phpunit directly with the env var:
QUERYGUARD_MODE=check vendor/bin/phpunit
```

Commit `tests/.queryguard-baseline.json` to git — PR diffs naturally show *"this test went from 4 to 17 queries."*

## Per-test budgets (optional)

```php
use QueryGuard\Attributes\QueryBudget;

#[QueryBudget(max: 5)]
public function test_index_is_fast(): void { /* ... */ }
```

## GitHub Actions

```yaml
- name: QueryGuard
  run: |
    php artisan queryguard:check --markdown > queryguard.md || EXIT=$?
    gh pr comment "$PR_NUMBER" --body-file queryguard.md
    exit ${EXIT:-0}
  env:
    PR_NUMBER: ${{ github.event.pull_request.number }}
    GH_TOKEN: ${{ github.token }}
```

## Configuration

```bash
php artisan vendor:publish --tag=queryguard-config
```

Then edit `config/queryguard.php` for tolerances, ignore patterns, slow-query threshold, and N+1 detection threshold.

## How it works

1. The PHPUnit extension hooks `testPrepared` / `testFinished` and registers a `DB::listen` callback that records every query per-test.
2. Each query's SQL is normalized into a stable signature (literals stripped, `IN (?,?,?)` collapsed, keywords lowercased) so the same logical query matches across runs.
3. At end of run, the recorded profiles are either written to `tests/.queryguard-baseline.json` (in `baseline` mode) or diffed against it (in `check` mode).
4. Any of the following exits the run non-zero:
   - A test's query count exceeds `baseline + tolerance`
   - The same query signature is executed more than `n_plus_one.threshold` times in a single test
   - A `#[QueryBudget]` is exceeded

Slow queries and new query signatures are reported as warnings (non-fatal) by default.

## License

MIT.
