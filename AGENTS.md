# Allure PHP Commons

## Runtime dependency boundary

Keep production code in `src/` independent of test frameworks. Packages
listed only in Composer's `require-dev` are for development and testing.
Production code must also avoid optional references to these packages, such
as checks guarded by `instanceof` or `class_exists()`.

Framework-specific status classification and assertion-detail extraction
belong in integration packages. Commons consumes the status and details
provided through `StatusDetectorInterface`.
