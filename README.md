# Laravel Smart Search API

A Laravel autocomplete demo using the database's native full-text index. It does
not use Elasticsearch or an external search service.

## Database

`search_records` stores the address text and has a MySQL/MariaDB FULLTEXT index
on `content`. The current database is MariaDB 10.4.32. The dataset size reported
for this demo is 35,938,603 records, above the 30-million-record requirement.
Do not run `SearchRecordSeeder` against this populated database.

Set the application's `DB_CONNECTION` to `mysql` and configure the database
connection in `.env`. Apply pending schema changes with:

```sh
php artisan migrate --no-interaction
```

SQLite is used by the automated tests and therefore skips the production
FULLTEXT index. The search SQL itself requires MySQL/MariaDB.

## Autocomplete

```http
GET /api/search?search=London%20Road
```

Search requests do not create usage records. The API returns at most ten
matches, an `execution_time_ms` value, and a short-lived `selection_url` on each
suggestion. Search uses parameter-bound MySQL Boolean FULLTEXT queries and
caches results in the local file cache for five minutes. Search terms are
optional matches, and the final term is a prefix match for autocomplete. This
does not provide edit-distance typo correction.

## Counting selected suggestions

The browser sends one POST only after a suggestion is clicked or chosen with
the keyboard:

```http
POST {selection_url}
```

The selection URL is signed, expires after ten minutes, and the endpoint is
rate-limited. A single-use key in the signed URL prevents browser retries from
counting the same selection twice. The global count is incremented in the
existing `search_api_usage_counters` table. Search results themselves continue
to use only `search_records`.

## Performance check

Performance must be measured on the configured production-like dataset and
hardware. The API reports request execution time, but the 50 ms target is not a
guarantee and should be confirmed with repeatable cold- and warm-cache tests.

## Run and test

Open `/search` for the autocomplete demo. Run the selection-accounting feature
tests with:

```sh
php artisan test --compact tests\Feature\ExampleTest.php
```
