# Screen-reader test script: <screen or command>

Copy this file for each new screen or command that needs a manual check, and
replace everything in angle brackets.

| | |
|---|---|
| Screen or command | <for example "Admin → Pages → Create", or `sightpress:install`> |
| Address or command line | <URL, or the command to run> |
| Milestone | <M0, M1, …> |
| Tester | <name> |
| Date | <YYYY-MM-DD> |
| Commit | <SHA of core, and of any plugin under test> |
| Screen reader and version | <NVDA 2026.x / JAWS 2026 / VoiceOver (macOS 16) / TalkBack / Orca 48> |
| Browser or terminal and version | <Firefox 1xx / Chrome 1xx / Safari 2x / Windows Terminal / GNOME Terminal> |
| Settings | <verbosity, punctuation level, browse or focus mode, zoom, colour scheme> |

Tool pairs to cover: NVDA + Firefox, JAWS + Chrome, VoiceOver + Safari and
Orca + Firefox; TalkBack + Chrome for narrow screens. Run at least three.

## Before you start

- <the state the screen needs: signed in as, which site, which data>
- Start with the screen reader's speech history cleared, so you can copy
  what it said.

## Steps

Write what a person does and what the screen reader should say. "Expected"
is the meaning, not the exact words: screen readers word things differently.

| # | Do this | Expected | Result | What you heard (if not Passed) |
|---|---|---|---|---|
| 1 | Open the page. | The page title and the main heading are announced. | | |
| 2 | List the landmarks. | Banner, navigation (with its name), main and content information. | | |
| 3 | Press Tab once. | "Skip to main content", link. | | |
| 4 | <next step> | <what should happen and be said> | | |

Result is **Passed**, **Failed** or **Blocked**.

## Also check

- Nothing is said twice, and nothing is announced that isn't on the screen.
- Every control says its role, name and state (for example "Save, button",
  "Title, required, edit text, invalid entry").
- Errors and messages are announced without moving focus unexpectedly.
- Focus never gets lost or trapped, and Escape closes anything that opened.

## Notes

<anything else: surprises, slow responses, wording that confused you>
