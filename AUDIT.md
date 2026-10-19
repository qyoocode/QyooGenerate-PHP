# Public repository audit

Inspected default-branch commit: `3bd1bf3cd28db971001fd0778dcf00500fec63f9`.
Prepared branch: `codex/public-repo-audit-20261018`. Classification: **reference implementation**.
Verdict: suitable for review as a reference generator after changes; production support and scan interoperability are not established.

## Findings and disposition

| Severity | Finding and evidence | Disposition |
|---|---|---|
| HIGH | Original `src/generate.php:22–25, 189–192` casts arbitrary decimal input to integer then pads without rejecting more than 36 bits; render loop consumes the first 36 characters. Oversized inputs can encode another ID. | Fixed by bounded string range validation before conversion; [`src/generate.php:4`](src/generate.php#L4), boundary/malformed tests. |
| MEDIUM | Original endpoint passes array-valued query input to string functions (`src/generate.php:19–25,185–186`), yielding a runtime error. | Scalar string validation; malformed requests now return 400. |
| MEDIUM | Original `src/generate.php:33–45,113–120` ignores mkdir/write/read errors, caches every request automatically, and allows concurrent partial-image access. | Cache disabled by default; optional directory/file-locked reads/writes, bounded reads, generic 503 failures. Deployment must supply quotas and a verified POSIX locking filesystem. Docker Desktop host-bind creation race reproduced and fixed by locking the stable directory before entry opens; corrected host-bind and Linux tmpfs tests PASS. See follow-up evidence below. |
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
- **Historical FAIL (subsequently fixed):** Initial test before endpoint extraction exited with `Code parameter is missing.` instead of running assertions; fixed. Concurrent-cache tests on Docker Desktop host bind mount produced different hashes (and once a cache worker error); this was investigated and fixed in the directory-lock follow-up below. The pre-fix failure was reproduced with `docker run --rm --network none -v "$PWD:/app" -w /app qyoo-task02-php-gd:8.3 php tests/cache.php .codex-work/new-cache-path`.
- **NOT RUN:** PHP 8.4/8.5, hosted CI execution, real camera/iOS detection round-trips, production hosting/load/memory exhaustion, write-failure injection after partial write (subsequent truncated-cache recovery is tested), sample GET body verification at the hosted domain. Local `php` is not installed; containers supplied the supported runtime.

## Release gates and owner decisions

Review the deliberate safety changes: out-of-range/invalid inputs are rejected, cache becomes opt-in, HTTP GET only, and the standalone helper constrains rendering dimensions to the endpoint's fixed 2048px intermediate. No payload serialization or historical endpoint appearance changed. Owner must decide whether to maintain this reference generator or sponsor a separately qualified current-renderer profile. Production operators must approve host limits, quotas, PHP patch updates and target-filesystem cache tests. Patent/trademark rights are not inferred from the BSD software grant.

Storage check before/after this small phase: approximately 19.87/19.89 GiB available (concurrent workspace activity affects the figure); checkout including retained test scratch grew from 24 KiB to approximately 124 KiB before final documentation. No heavy build/install was started below the 20 GiB floor. Tiny failed-cache artifacts remain ignored under `.codex-work/` as reproducible evidence; no quarantine or cleanup was performed.

## Follow-up review: cached PNG validation

MEDIUM: signature and byte-length checks alone accepted an eight-byte PNG signature as a successful image. A failing regression reproduced this. Cached content now must report PNG format and fixed 512 × 512 dimensions before a full GD decode succeeds. Invalid content is regenerated under the existing exclusive file lock; generation finishes before existing cache bytes are replaced. Regression tests cover signature-only bytes, arbitrary corrupt bytes, a truncated valid PNG, and a valid PNG of the wrong dimensions, including persistence of the repaired result. The full PHP 8.3 syntax/unit/HTTP/Linux-tmpfs cache suite was rerun successfully; the then-unresolved Docker Desktop bind-mount cache failure is addressed below.


## Follow-up review: Docker Desktop cache creation race

**FACT:** The existing four-process cache regression failed again before this change (`.codex-work/cache-followup-01`), returning two SHA-256 values for one newly created entry. Instrumentation of the unchanged algorithm showed two workers obtaining `LOCK_EX` on the same reported inode at 1792378020.096975 and 1792378020.097930, both before the first writer finished at 1792378020.142430. Therefore the palette randomness exposed a real exclusion failure; the hash assertion was valid.

A minimal probe excluding Qyoo rendering reproduced overlapping 100 ms lock intervals for concurrently created files: `[1792378027.652613,1792378027.752992]` and `[1792378027.653311,1792378027.753413]`. Precreated-file intervals serialized. Exclusive `x+b` creation with `r+b` fallback still overlapped; directory-lock intervals serialized. These observations identify a Docker Desktop bind-mount first-creation locking failure, not a decoder/geometry defect or proof of an internal Docker cause. Ignored probes and diagnostic logs remain under `.codex-work/` for recovery; no generated geometry or palette changed.

**Fix:** Lock the stable cache directory before any entry open and hold it through image I/O, retaining the entry lock. This avoids relying on newly created file exclusion and serializes all IDs in a cache directory. Directory/file locking support is now an explicit POSIX deployment requirement; unsupported locking fails closed. The regression additionally checks that all worker responses match the final persisted image.

**Verification, pre-existing PHP 8.3.33/GD image only, no build/install/pull:**

```sh
docker run --rm --network none -v "$PWD:/app" -w /app qyoo-task02-php-gd:8.3 sh -c 'php -l src/generate.php && php tests/test.php && php tests/http.php && php tests/cache.php .codex-work/cache-fixed-02 && php tests/cache.php .codex-work/cache-fixed-03 && php tests/cache.php .codex-work/cache-fixed-04'
docker run --rm --network none -v "$PWD:/app" -w /app qyoo-task02-php-gd:8.3 php tests/cache.php .codex-work/cache-fixed-05
mkdir -p .codex-work/cache-linux-followup
docker run --rm --network none --tmpfs /app/.codex-work/cache-linux-followup -v "$PWD:/app:ro" -w /app qyoo-task02-php-gd:8.3 php tests/cache.php .codex-work/cache-linux-followup/run
git diff --check
```

All listed runtime checks PASS. An initial Linux-tmpfs invocation failed at container startup because its mountpoint was absent under the read-only source bind; creating that empty mountpoint and rerunning passed. This was a test setup error, distinct from the reproduced cache locking defect. Five fresh host-bind cache runs passed (the last also checks persisted hash); the updated regression passed on tmpfs. PHP 8.4/8.5, other operating systems/filesystems and production load remain NOT RUN. This is correctness evidence for the tested runtime, not high-throughput or cross-host qualification.

Storage remained approximately 19.9 GiB free throughout this bounded follow-up (concurrent workspace use affects totals). No heavy work started below the 20 GiB floor. Ignored diagnostic scripts, tiny cache PNGs and logs remain as recovery evidence; scratch grew from 96 KiB to 224 KiB (+128 KiB), and the final free-space check was 20,826,056 KiB (about 19.86 GiB). No cleanup, deletion, push or production change occurred.
