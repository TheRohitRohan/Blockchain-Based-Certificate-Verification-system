# Test Report — 2026-03-21

## Summary
| Metric | Value |
|---|---|
| Total Tests | 37 |
| Passed | 32 |
| Failed | 0 |
| Skipped | 5 |
| Code Coverage | Not available (coverage driver missing) |

## Results by Module
### ✅ Auth
- Unit tests: pass
- Integration tests: pass
- Coverage: N/A (driver missing)
- Failed tests: none

### ✅ Cache
- Unit tests: pass
- Integration tests: pass
- Coverage: N/A
- Failed tests: none

### ✅ MetadataService
- Unit tests: pass
- Integration tests: pass
- Coverage: N/A
- Failed tests: none

### ✅ PDFService
- Unit tests: pass
- Integration tests: pass (warnings about invalid PDF data expected in test fixtures)
- Coverage: N/A
- Failed tests: none

### ✅ SignatureService
- Unit tests: pass
- Integration tests: skipped (OpenSSL keygen not available)
- Coverage: N/A
- Failed tests: none

### ✅ VerificationEngine
- Unit tests: pass
- Integration tests: pass (with expected invalid upload/path warnings)
- Coverage: N/A
- Failed tests: none

### ✅ CertificateService
- Unit tests: pass
- Integration tests: skipped (MySQL-specific flows)
- Coverage: N/A
- Failed tests: none

### ✅ PublicVerificationService
- Unit tests: pass
- Integration tests: pass
- Coverage: N/A
- Failed tests: none

### ✅ Blockchain
- Unit tests: pass (mock-mode/skip tolerant)
- Integration tests: pass
- Coverage: N/A
- Failed tests: none

### ✅ ComparisonEngine
- Unit tests: placeholder (pass)
- Integration tests: skipped (requires full PDF parsing)
- Coverage: N/A
- Failed tests: none

## Action Items
- Install/enable a code coverage driver (e.g., Xdebug or pcov) and rerun PHPUnit to generate coverage.
- If desired, unskip integration tests that rely on MySQL/OpenSSL/PDF parsing once those dependencies are available.
- Review warning outputs from PDF/hash/signature tests; these are expected from fixture stubs but can be silenced with more representative fixtures when environment supports it.
