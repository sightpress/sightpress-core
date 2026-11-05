# Accessibility test scripts and statement

Automated gates (axe-core in the browser, page-structure, reflow and keyboard
checks, and the PHP assertions) catch a lot, but not everything. These scripts
are for the people who check the rest: blind and low-vision testers using
real screen readers, keyboard-only testers, and reviewers.

| File | What it is |
|---|---|
| [`accessibility-statement.md`](accessibility-statement.md) | The accessibility statement for SightPress itself: the standard it meets, what is known not to work yet, and how to report a problem. |
| [`screen-reader-test-script-template.md`](screen-reader-test-script-template.md) | Copy this for every new screen or command that needs a manual check. |
| [`m0-walkthrough.md`](m0-walkthrough.md) | The M0 walkthrough: the base admin layout and the placeholder form. A done-bar item for M0. |
| [`installer-test-script.md`](installer-test-script.md) | The installer (`sightpress:install`) read by a screen reader in a terminal. |
| [`results/`](results/) | Completed runs, one file per tester, tool pair and date. |

## Running the fixture admin

The M0 screens run on the core's own test app, so no database or install is
needed:

```bash
composer install
php -S 127.0.0.1:8123 tests/Fixtures/App/public/index.php
```

Then open `http://127.0.0.1:8123/admin`. The test app lets you in without
signing in (sign-in arrives in M1). Add `?ui_locale=he-IL` or
`?ui_locale=ar-SA` to any address to see the right-to-left layout.

## Recording a result

Copy the script into `results/` as
`YYYY-MM-DD-<script>-<screen-reader>-<browser>.md`, fill in the header and the
result column, and commit it. Every step is **Passed**, **Failed** or
**Blocked**, with what you heard when it isn't Passed. A failure is a bug:
open an issue with the steps, the tool versions and what you heard, and link
it from the result.
