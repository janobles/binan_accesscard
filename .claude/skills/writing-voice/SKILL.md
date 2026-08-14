---
name: writing-voice
description: Use when writing or editing anything readers see as prose - documentation under docs/, the README, agent context files, or copy that ships in the application interface (labels, help text, empty states, error messages, button text). Carries the two registers this repo writes in and the patterns banned in both. Not for code, comments, or docblocks; those follow docs/reference/comment-standard.md.
---

# Writing voice

This repo writes in two registers. They share a set of banned patterns and
almost nothing else. Pick the register from the audience, not from the file
extension.

| You are writing | Register | Reader |
|-----------------|----------|--------|
| `docs/`, `README.md`, `AGENTS.md`, spec and plan documents | Handbook | A developer who does not know this system |
| Labels, help text, empty states, errors, button text, page titles | Interface | CSWD staff doing their job |

## The handbook register

Write the way good documentation for a technology reads: a person explaining
something they know well to a person who needs to use it.

**Assume technical skill, not familiarity.** The reader can read PHP and knows
what a foreign key is. They do not know what a barangay is, why a subsidy type
is not a service, or why this application has no migrations. Introduce a concept
before you use it. `docs/00-introduction.md` holds the glossary that lets later
chapters say "barangay" and "distribution batch" plainly; if a term is not in
there and it is not general programming vocabulary, define it on first use.

**Explain why, not only what.** The reader is usually deciding whether to change
something. A rule with no reasoning attached gets worked around by the next
person who finds it inconvenient. When you state a constraint, say what breaks
without it:

> Routes carry no role prefix. The alternative, a URL space per role, produced
> parallel controllers and a sidebar edited in three places.

**Use second person and contractions.** "You'll need MySQL running" reads like a
person. "The user must ensure MySQL is running" reads like a compliance
document. The setup and networking chapters are the model here.

**Prefer a worked example to an abstract description.** Show the actual command,
the actual manifest entry, the actual failure message. One concrete example
teaches more than three paragraphs of description, and it can be verified.

**Name the failure, not just the fix.** A reader who knows what a broken
`app.baseURL` looks like (page loads, CSS 404s, form posts bounce) can diagnose
it. A reader who only knows the rule cannot.

**Cut anything that only restates its heading.** If the first sentence under
"## Backup and restore" is "This section covers backup and restore", delete it
and start with the content.

Cites are backtick-wrapped and repo-relative: `` `app/Libraries/DashboardPageBuilder.php:42` ``.
`scripts/check-doc-cites.sh` verifies every one of them resolves, so a cite with
a guessed line number fails the build rather than misleading a reader.

## The interface register

Text that ships in the application is read by CSWD staff mid-task. They are not
reading; they are looking for the one thing they need.

**Noun labels, not sentences.** "Head of family", not "Enter the head of the
family here".

**Help text only where the behaviour is invisible.** If a field is called
"Barangay" and holds a barangay, it needs no explanation. If confirming an
import writes several hundred rows that cannot be undone, say so. Per-field
explanatory prose is noise that trains people to skip all text, including the
warning that mattered.

**Use the words the user already uses.** The user sees "Head of family", never
`headID`. They see "Access card", not "QR control record". Schema vocabulary
stops at the controller.

**Errors say what to do next.** "Row 14: birthdate is in the future" beats
"Validation failed".

Related decisions worth matching: `docs/20-frontend.md` for the placeholder and
button copy that is already standardised, such as "Search all records..." versus
"Search this page...".

## Banned in both registers

- **Em dashes.** Use a comma, a colon, or a full stop. The one exception is the
  GitHub issue format in the `code-review` skill, which is a fixed external
  format.
- **`---- section ----` and `==== section ====` dividers.** Use a heading or a
  blank line.
- **`@author`, `@created`, `@version`, `@package`.**
- **Text recording that someone asked for a change.** Documentation describes
  what is, not the history of who wanted what. That history is in git.
- **AI-slop register.** No "it is important to note", no "in order to", no
  "delve", no "leverage" where "use" works, no three-adjective openers, no
  sentence whose only job is to announce the next sentence.

## Checklist before you commit prose

- Every term a newcomer would not know is either in the glossary or defined on
  first use.
- Every rule states its consequence.
- No em dashes. Search for them: `grep -n '—' <file>`.
- Every cite resolves: `bash scripts/check-doc-cites.sh`.
- Nothing restates its own heading.
