# Public repository audit

Inspected default-branch commit: `3bd1bf3cd28db971001fd0778dcf00500fec63f9`.
Prepared branch: `codex/public-repo-audit-20261018`. Classification: **reference implementation**.
Verdict: suitable for review as a reference generator after changes; production support and scan interoperability are not established.

## Findings and disposition

| Severity | Finding and evidence | Disposition |
|---|---|---|
| HIGH | Original `src/generate.php:22–25, 189–192` casts arbitrary decimal input to integer then pads without rejecting more than 36 bits; render loop consumes the first 36 characters. Oversized inputs can encode another ID. | Fixed by bounded string range validation before conversion; [`src/generate.php:4`](src/generate.php#L4), boundary/malformed tests. |
| MEDIUM | Original endpoint passes array-valued query input to string functions (`src/generate.php:19–25,185–186`), yielding a runtime error. | Scalar string validation; malformed requests now return 400. |
| MEDIUM | Original `src/generate.php:33–45,113–120` ignores mkdir/write/read errors, caches every request automatically, and allows concurrent partial-image access. | Cache disabled by default; optional locked reads/writes, bounded reads, generic 503 failures. Deployment must supply quotas and a verified local locking filesystem. **Docker Desktop host bind mount concurrency test FAILS**; Linux tmpfs test PASSES. See README limitations. |
| MEDIUM | Original README describes a hosted PNG endpoint. Audit HEAD request returned 200 `text/html`. | Hosted image-service claim removed. A HEAD response alone does not prove GET behavior; the endpoint must not be advertised as a verified image generator. |
| MEDIUM | Historical PHP geometry differs from the current web renderer. | Preserved and documented instead of silently changing appearance. Current web carrier/export ratio is 204/256; PHP is 256/512, with different dot centers/radii. Shared row-major/MSB-first 36-bit ordering is verified by source and focused tests. Camera round-trip qualification remains NOT RUN. |
| LOW | PHP 5.6 compatibility assertion, absent tests/CI, commented debug settings and generic README boilerplate. | PHP 8.3+ 64-bit documented, focused tests and CI matrix added, misleading/debug introduction removed. |

## History, licensing and exposure

Reviewed all three reachable commits (`git log --all --stat`, source/README diffs) and all tracked paths. Baseline has only `LICENSE`, `README.md`, `src/generate.php`: no checkpoint, vendored dependency, binary, photograph, editor-state or third-party asset. BSD-3-Clause text and copyright are complete and retained unchanged. PHP/GD are external runtime dependencies; there is no Composer dependency or lockfile to update.

A non-disclosing history pattern scan checked private-key blocks, common cloud/GitHub credential formats and developer home paths. No credential-pattern/private-key hits were found; the only home-path match was the generic README `/home/username/public_html/` example. This is a limited pattern/source review, not proof that all possible secrets or legal issues are absent. No history rewrite, file deletion, push, deployment, install, image pull or production-project edit occurred.

## Exact verification commands

Commands ran from this repository using the **pre-existing** local `qyoo-task02-php-gd:8.3` image (PHP 8.3.33 with GD), no network/pull required:

```sh
docker run --rm --network none -v "$PWD:/app:ro" -w /app qyoo-task02-php-gd:8.3 php -v
docker run --rm --network none -v "$PWD:/app:ro" -w /app qyoo-task02-php-gd:8.3 php -l src/generate.php
docker run --rm --network none -v "$PWD:/app:ro" -w /app qyoo-task02-php-gd:8.3 php tests/test.php
docker run --rm --network none -v "$PWD:/app:ro" -w /app qyoo-task02-php-gd:8.3 php tests/http.php
docker run --rm --network none --tmpfs /app/.codex-work/cache-linux -v "$PWD:/app:ro" -w /app qyoo-task02-php-gd:8.3 php tests/cache.php .codex-work/cache-linux/run
git diff --check
curl --max-time 15 -s -I https://id.qyoo.com/511305600
```

- **PASS:** PHP syntax, decimal/binary boundaries, array/malformed/oversized input rejection, PNG signature/dimensions, historical row-major bit position, cache errors, HTTP 200/400/405 and headers, concurrent cache and corrupt/signature-only/truncated/wrong-dimension PNG repair on Linux tmpfs, whitespace check.
- **FAIL:** Initial test before endpoint extraction exited with `Code parameter is missing.` instead of running assertions; fixed. Concurrent-cache tests on Docker Desktop host bind mount produced different hashes (and once a cache worker error); cache use on that storage remains unsupported. Reproduce with `docker run --rm --network none -v "$PWD:/app" -w /app qyoo-task02-php-gd:8.3 php tests/cache.php .codex-work/new-cache-path`.
- **NOT RUN:** PHP 8.4/8.5, hosted CI execution, real camera/iOS detection round-trips, production hosting/load/memory exhaustion, write-failure injection after partial write (subsequent truncated-cache recovery is tested), sample GET body verification at the hosted domain. Local `php` is not installed; containers supplied the supported runtime.

## Release gates and owner decisions

Review the deliberate safety changes: out-of-range/invalid inputs are rejected, cache becomes opt-in, HTTP GET only, and the standalone helper constrains rendering dimensions to the endpoint's fixed 2048px intermediate. No payload serialization or historical endpoint appearance changed. Owner must decide whether to maintain this reference generator or sponsor a separately qualified current-renderer profile. Production operators must approve host limits, quotas, PHP patch updates and target-filesystem cache tests. Patent/trademark rights are not inferred from the BSD software grant.

Storage check before/after this small phase: approximately 19.87/19.89 GiB available (concurrent workspace activity affects the figure); checkout including retained test scratch grew from 24 KiB to approximately 124 KiB before final documentation. No heavy build/install was started below the 20 GiB floor. Tiny failed-cache artifacts remain ignored under `.codex-work/` as reproducible evidence; no quarantine or cleanup was performed.

## Follow-up review: cached PNG validation

MEDIUM: signature and byte-length checks alone accepted an eight-byte PNG signature as a successful image. A failing regression reproduced this. Cached content now must report PNG format and fixed 512 × 512 dimensions before a full GD decode succeeds. Invalid content is regenerated under the existing exclusive file lock; generation finishes before existing cache bytes are replaced. Regression tests cover signature-only bytes, arbitrary corrupt bytes, a truncated valid PNG, and a valid PNG of the wrong dimensions, including persistence of the repaired result. The full PHP 8.3 syntax/unit/HTTP/Linux-tmpfs cache suite was rerun successfully; the documented Docker Desktop bind-mount cache limitation remains.
