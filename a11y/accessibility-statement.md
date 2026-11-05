# Accessibility statement for SightPress

SightPress is a content management system designed with blind and low-vision
creators and audiences in mind. This statement covers the SightPress admin
and the pages SightPress generates. Each site built with SightPress publishes
its own accessibility statement, created from a template when the site is set
up.

## How accessible SightPress is

SightPress aims to meet the **Web Content Accessibility Guidelines (WCAG)
2.2 at level AA**, and level AAA where practical. Accessibility can't be
turned off: every admin screen and every public page is checked automatically
before each release, and the checks can't be skipped or loosened.

You should be able to:

- use every admin task with a keyboard alone and with a screen reader
  (NVDA, JAWS, VoiceOver, TalkBack and Orca are tested);
- zoom to 400% with no loss of content and no scrolling in two directions;
- use light or dark mode, higher contrast and forced colours;
- turn motion off with your system's reduced-motion setting;
- use the admin in any of its 11 launch languages, and in right-to-left
  languages;
- install and set SightPress up from the terminal with a screen reader: plain
  one-line questions, the default said in words, numbered choices and
  progress as text.

## What is checked, and how

- **On every change:** axe-core rules for WCAG 2.2 A and AA on every admin
  route, in light and dark mode, at 320 pixels wide, and in two right-to-left
  test languages (Hebrew and Arabic); page structure (landmarks, one main
  heading, a skip link first); a keyboard-only journey with a visible focus
  indicator at every stop; and tests of the error summary, field
  descriptions and live regions.
- **On every milestone:** a blind tester runs the screen-reader scripts in
  this folder on at least three screen readers, and low-vision and
  keyboard-only testers run their checks.

## Known limitations

SightPress is in its first milestone (M0). Today the admin has a base layout
and a placeholder dashboard; content editing, media and site settings arrive
in later milestones. The interface strings in languages other than English
(United Kingdom) are drafts until a fluent reviewer checks them.

We know of no accessibility barriers in what exists today. If you find one,
please tell us.

## Reporting a problem

Open an issue at
<https://github.com/sightpress/sightpress-core/issues/new> and say what you
were doing, what you expected and what happened, and which browser, screen
reader or other assistive technology you use. If you can't use GitHub,
email the maintainers at the address in
[`SECURITY.md`](../SECURITY.md) and put "Accessibility" in the subject. An
accessibility barrier is treated as a bug that blocks a release.

## Preparation of this statement

This statement was prepared on 5 November 2026 and is reviewed at every
milestone.
