# M0 walkthrough: the base admin layout and the placeholder form

The screen-reader walkthrough in the M0 done bar (`PLAN.md` §8,
`M0-HUMAN-REVIEW.md` §4.1). Run it on at least three of: NVDA + Firefox,
JAWS + Chrome, VoiceOver + Safari, Orca + Firefox; add TalkBack + Chrome at
a narrow width. Use one copy of this file per tool pair (see
[`README.md`](README.md) for how to start the fixture admin and where to put
results).

| | |
|---|---|
| Tester | |
| Date | |
| Commit (core) | |
| Screen reader and version | |
| Browser and version | |
| Settings | |

## Part 1: the dashboard (`/admin`)

| # | Do this | Expected | Result | What you heard (if not Passed) |
|---|---|---|---|---|
| 1 | Open `http://127.0.0.1:8123/admin`. | "Dashboard: SightPress admin" is announced as the page title; reading starts at the top. | | |
| 2 | List the landmarks (NVDA: Insert+F7; JAWS: Insert+Ctrl+R; VoiceOver: rotor; Orca: Insert+F7 or landmarks list). | Banner, navigation named "Admin", main and content information (footer). No others. | | |
| 3 | List the headings. | One level-1 heading, "Dashboard". | | |
| 4 | Press Tab once. | "Skip to main content", link. It is visible on screen too. | | |
| 5 | Press Enter on the skip link. | Focus moves to the main content; the next thing read is the "Dashboard" heading. | | |
| 6 | Tab through the page to the end. | Skip link, then "Dashboard" link in the Admin navigation announced as the current page. Nothing else takes focus, and focus never disappears. | | |
| 7 | Read the page from top to bottom. | Brand "SightPress", navigation, "Dashboard" heading, the welcome text, then the footer text. The order matches what is on screen. | | |

## Part 2: the placeholder form (`/admin/fixture-form`)

| # | Do this | Expected | Result | What you heard (if not Passed) |
|---|---|---|---|---|
| 8 | Open `http://127.0.0.1:8123/admin/fixture-form`. | "Fixture form: SightPress admin", heading "Fixture form". | | |
| 9 | Tab to the field. | "Title (required), edit text", then its help: "The name shown at the top of the page." | | |
| 10 | Leave it empty and press Enter (or Tab to "Save" and press it). | The page reloads; focus is on the error summary, and "There is a problem", "1 problem needs fixing" and the link "Enter a title" are read. | | |
| 11 | Activate the link "Enter a title". | Focus moves to the Title field, announced as invalid, with "The name shown at the top of the page. Enter a title". | | |
| 12 | Type "Opening hours" and press Enter. | The page reloads with no error summary; the message "Saved "Opening hours"." is read (it is in a polite live region, so it may be read after the title and heading). | | |

## Part 3: right to left

| # | Do this | Expected | Result | What you heard (if not Passed) |
|---|---|---|---|---|
| 13 | Open `/admin?ui_locale=he-IL`. | The layout is mirrored. The interface text is English, and the screen reader reads it with an English voice, not a Hebrew one. | | |
| 14 | Repeat steps 2 to 6. | Same results as in Part 1, in mirrored order on screen. | | |

## Part 4: the installer

Run [`installer-test-script.md`](installer-test-script.md) with the same
screen reader in a terminal.

## Overall

| | |
|---|---|
| Result | Passed / Failed / Blocked |
| Issues opened | |
| Notes | |
