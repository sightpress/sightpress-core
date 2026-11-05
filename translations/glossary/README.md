# Translation glossary

`glossary.csv` keeps the interface's terms the same everywhere, in every launch
locale (PLAN §3.1). Use these words in every catalogue of the core and the
official plugins. They follow the WCAG authorised translations and the words
screen readers (NVDA, JAWS, VoiceOver, TalkBack, Orca) already use in each
language, so people hear the terms they know.

- One row per term. `en-GB` is the source; regional columns (`en-US`, `pt-BR`,
  `es-419`, `fr-CA`, `de-CH`) are filled only where the region uses another word.
  An empty regional cell means "same as the base language".
- `status` is `draft` until a fluent reviewer for every language has checked the
  row, then `reviewed`. Draft terms may be used, but catalogue targets that use
  them stay `needs-review-translation` until the row is reviewed.
- Add a term when a new feature introduces one, in the same change.
