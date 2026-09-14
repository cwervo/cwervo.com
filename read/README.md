# `read/` daily reading recommender

This folder contains a lightweight PHP-based daily reading recommender for `cwervo.com/read`.

## Files

- `index.php` — serves one reading recommendation per device per UTC day.
- `fetch.php` — ingests candidate items from API-friendly sources and stores them locally.
- `feedback.php` — asks one follow-up question tied to the latest suggestion and updates preferences.
- `lib.php` — shared JSON, HTTP, ranking, and device-profile helpers.
- `store/` — repo-local JSON storage for candidates and per-device profiles.
- `wasm/scorer-loader.js` — WASM-ready on-device reranking interface with deterministic JS fallback.

## Setup

This feature is dependency-free PHP and can run on any host with PHP 8.1+ and write access to `read/store/`.

1. Copy the repository to a PHP-capable web server.
2. Ensure the web server user can write to:
   - `read/store/candidates.json`
   - `read/store/devices/`
3. Visit `/read/` or `/read/index.php`.

> GitHub Pages is not suitable for this feature: it cannot execute PHP and it also cannot persist the runtime JSON/device state under `read/store/`. Use a PHP host with writable local storage for the live recommender.

## Running the fetch step

Manual run:

```bash
php read/fetch.php
```

Example cron entry (daily at 05:20 UTC):

```cron
20 5 * * * cd /path/to/cwervo.com && /usr/bin/php read/fetch.php >> /var/log/cwervo-read-fetch.log 2>&1
```

## Source coverage

`fetch.php` pulls from these API-friendly sources and tolerates partial failures:

- OpenAlex works API
- Crossref works API
- arXiv API
- Internet Archive advancedsearch API

If one source times out or fails, the script still writes whatever candidates it successfully retrieved.

## Data layout

```text
read/
├── feedback.php
├── fetch.php
├── index.php
├── lib.php
├── store/
│   ├── .gitkeep
│   ├── .gitignore
│   ├── candidates.json
│   └── devices/
│       └── .gitkeep
└── wasm/
    └── scorer-loader.js
```

### Candidate schema

Stored in `read/store/candidates.json` with keys such as:

- `id`
- `source`
- `title`
- `authors`
- `year`
- `abstract`
- `url`
- `type`
- `topics`
- `score_base`
- `fetched_at`

### Device profile schema

Each device gets a JSON file in `read/store/devices/{device_hash}.json` containing:

- daily suggestion history
- explicit feedback entries
- topic weights
- lightweight preference values (for example, technicality)

## Personalization flow

1. The server picks a baseline candidate from the local pool.
2. The browser stores an opaque device token in `localStorage` and sets a first-party cookie.
3. `wasm/scorer-loader.js` reranks the server’s top candidates on-device.
4. The chosen item is posted back as score hints and locked for the day.

A real `.wasm` module can be dropped into `read/wasm/scorer.wasm` later; the current loader is already wired for that path and falls back to deterministic JS when unavailable.

## Rate limits and operational notes

- These source APIs are public but still rate-limited. Keep cron frequency modest (daily is fine).
- No API keys are required for the included sources.
- Corrupt or missing JSON files are handled defensively by falling back to empty defaults.

## Security and privacy notes

- The device identifier is a SHA-256 hash of a first-party local device token stored in `localStorage` and echoed back through a first-party cookie.
- No IP address or third-party identifier is used for device hashing.
- No third-party analytics or external personalization service is involved.
- Template output is HTML-escaped on the PHP side.

## Limitations

- GitHub Pages cannot execute PHP or persist writable JSON state, so this feature needs a PHP host with local write access for live use.
- The current on-device scorer uses a WASM-ready interface with a deterministic JS fallback rather than a compiled production model.
- Per-device persistence depends on first-party cookies and local storage being available.
- Title-similarity deduplication is intentionally simple and conservative.
