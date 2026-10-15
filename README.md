# BCM Developer Test — submission

My answers to the BCM developer test: five code questions with working code and test evidence, plus written answers to all ten questions in `ANSWERS.md`.

## What's here

| Folder / file | Question |
|---|---|
| `ANSWERS.md` | All ten answers |
| `q01-php/` | Q1 corrected search template + tests |
| `q04-css/` | Q4 responsive layout + screenshots |
| `q05-html/` | Q5 corrected HTML + validator output |
| `q06-js/` | Q6 button behaviour + jsdom tests |
| `q09-api/` | Q9 signup function + tests |

## Run the checks yourself

Install once: `npm i jsdom vnu-jar` (git-ignored, never submitted).

1. `cd q01-php && php tests/run-tests.php` — 23 [PASS] and "All checks passed"; proves the Q1 fixes cover missing params, pages 2 and 3, no results, and multiple `[ad]` markers.
2. `cd q09-api && php test.php` — 13 [PASS]; proves Q9 never loses or duplicates a signup, including the retry after the service already accepted one.
3. `cd q06-js && NODE_PATH=../node_modules node test.js` — 19 [PASS]; proves the Q6 click, separator and toggle behaviour end to end in jsdom.
4. `java -jar node_modules/vnu-jar/build/dist/vnu.jar q05-html/index.html` — 0 errors, 0 warnings; the fixed HTML is valid (the original's errors are listed in `q05-html/original-validation.txt`).
5. Open `q04-css/index.html` and resize across 768px: A spans two columns above it, E/F/H vanish below. The screenshots in `q04-css/` were taken with headless Chrome at 1280×800 and 375×812.

## AI use

Claude (Anthropic) drafted the code and the written answers; I verified everything by running the suites above, and that process caught two bugs in the first drafts (a function redeclare in the Q1 tests, a CSS `order` specificity bug in Q4). Limits: Q1 and Q9 run against small stand-ins, not a real WordPress install, and Q2, Q3, Q7, Q8 and Q10 are written reasoning, not experiments.

## Time

**[FILL IN: hours spent]** — **[FILL IN: what you personally reviewed or changed]**
