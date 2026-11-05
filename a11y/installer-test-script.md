# Screen-reader test script: the installer (`sightpress:install`)

Checks the installer with a screen reader reading a terminal (`PLAN.md`
§3.4, "Terminal accessibility"). Use NVDA or JAWS with Windows Terminal,
VoiceOver with Terminal on macOS, or Orca with GNOME Terminal.

| | |
|---|---|
| Tester | |
| Date | |
| Commit (core and skeleton) | |
| Screen reader and version | |
| Terminal and version | |

## Before you start

Create a project from the skeleton without running the installer, and have
PostgreSQL running (the skeleton's `docker compose up -d` starts one):

```bash
SIGHTPRESS_SKIP_INSTALL=1 composer create-project sightpress/skeleton my-site
cd my-site
docker compose up -d
```

## Steps

| # | Do this | Expected | Result | What you heard (if not Passed) |
|---|---|---|---|---|
| 1 | Run `php bin/console sightpress:install`. | "SightPress installer.", what Part 1 and Part 2 are, and "Each question states its default. Press Enter to accept it." | | |
| 2 | Listen to step 1. | "Step 1 of 7: Requirements", then one line per check starting with "Passed:" or "Failed:". No spinner, no screen clearing, no colour-only meaning. | | |
| 3 | At "Application environment", listen, then type 9 and press Enter. | Numbered choices, then "Type a number from 1 to 2 (default: 1, Production (prod))". After 9: an error that names the field and the valid numbers, then the same question again. | | |
| 4 | Press Enter to accept defaults until the database password. | Each question states its default in words before you answer. | | |
| 5 | At "Database password", type it. | "Typing won't be shown, and nothing is printed as you type." Nothing is echoed. | | |
| 6 | Give a wrong password on purpose. | "Failed: Database: SightPress could not connect to …" with the server's reason, without the password, then the questions again with your answers as defaults. | | |
| 7 | At "Which official plugins do you want to install?", listen to the list first. | One numbered line per official plugin with a one-line description and "Installed" or "Not installed". Default: none. | | |
| 8 | Press Enter. | "No plugins will be installed." | | |
| 9 | Finish the remaining steps. | Every step announces "Step N of 7", and the run ends with "Part 1 is done" and numbered next steps, including the official plugins you can install later with their commands. | | |
| 10 | Run it again. | "SightPress is already installed. The setup runs again, and your earlier answers are the defaults." | | |
| 11 | Run `NO_COLOR=1 php bin/console sightpress:install --no-ansi` and listen. | Nothing changes for the screen reader: the output was already plain text. | | |

## Overall

| | |
|---|---|
| Result | Passed / Failed / Blocked |
| Issues opened | |
| Notes | |
