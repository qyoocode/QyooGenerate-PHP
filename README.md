# Qyoo PHP image generator

A reference PHP/GD generator for the historical Qyoo visual carrier. It accepts IDs from **0 through 68,719,476,735** and renders a 512 × 512 PNG containing 36 payload bits, most significant bit first in row-major order. Exactly 36 binary digits are also accepted; that form takes precedence over decimal parsing. Leading zeroes are accepted for decimal input up to 36 characters.

This is an image generator, not a Qyoo resolver, ID allocator, ownership registry, scanner, or Manifest service. Base36 routing used by other Qyoo applications is not accepted by this endpoint and does not change the payload bits.

## Status and geometry

**Reference implementation.** This renderer retains its historical circle/square proportions, dot placement, randomized background palette and supersampling. Its carrier diameter is half the output width. It is **not geometrically identical** to the current Qyoo web renderer's `historical_svg_204` profile, which uses a 204-unit carrier in a 256-unit export. Binary payload ordering agrees; pixel equivalence and end-to-end iOS detection are not claimed. Do not use this repository to infer support for the proposed signature-cell/CRC-10 symbol.

## Run locally

Use **64-bit PHP 8.3 or newer** with GD and PNG support. CI targets PHP 8.3–8.5; only 8.3 was exercised in the accompanying local audit. PHP 5.6 is unsupported.

```sh
php -m                  # must include gd
php -S 127.0.0.1:8080 -t src
```

Open `http://127.0.0.1:8080/generate.php?code=511305600`, or supply an exactly 36-character binary payload. Invalid input returns 400; non-GET methods return 405; unavailable rendering/cache storage returns 503 with a generic message. Successful responses use `image/png` and `nosniff`.

The PHP development server is for local testing. A production host must configure its own PHP worker memory/concurrency limits, request-size limits, rate limits and HTTPS. Generation uses a fixed 2048 × 2048 intermediate image (approximately 16 MiB of raw pixel storage) and a fixed 512 × 512 output; reserve additional PHP/GD memory per worker. Requests cannot choose dimensions or colors. The historical random palette is preserved, so repeated uncached requests can differ.

The previous README named `id.qyoo.com` as a hosted image endpoint. An audit HEAD request to `https://id.qyoo.com/511305600` returned HTTP 200 with `text/html`, not PNG. This repository does not establish its current operator or deployment. Host this script yourself; do not assume a resolver URL serves PNG images.

## Optional cache

Caching is **off by default** to prevent arbitrary ID requests from filling disk. To enable it, set `QYOO_CACHE_DIR` to a dedicated absolute directory **outside the web document root**, writable only by the service account. The script creates a missing directory with mode 0770 subject to the process umask. All endpoint readers/writers lock the image file during reads and writes; reported I/O failures produce 503. Use a local filesystem with coherent file locks and metadata. The concurrency test failed on a Docker Desktop macOS host bind mount and passed on Linux tmpfs; Docker host bind mounts and network filesystems are unsupported cache locations. Run the cache test on the intended deployment filesystem before enabling it. Invalid cached PNG signatures/sizes are rejected. Do not expose the directory as static web content or share it with untrusted writers.

Apply a disk quota and operational retention policy before enabling this unbounded cache. There is no automatic eviction. Existing legacy cache files are not automatically migrated. Responses use `Cache-Control: no-store` because the palette is nondeterministic and this script does not implement HTTP revalidation. No external production services or package dependencies are required.

## Tests

```sh
php -l src/generate.php
php tests/test.php
php tests/http.php
php tests/cache.php .codex-work/test-cache
```

The cache test requires a new directory for each invocation and retains its tiny test artifacts. Tests cover ID boundaries, malformed/array inputs, row-major historical geometry, PNG dimensions, HTTP status/headers, concurrent cache creation and invalid-cache handling. They do not establish camera scan accuracy. See [AUDIT.md](AUDIT.md) for verification limits.

## License

[BSD-3-Clause](LICENSE), copyright 2024 qyoo. PHP and the installed GD library are external runtime dependencies, not vendored here; distributors must honor their respective licenses. The software license makes no additional claim about patents or trademarks.
