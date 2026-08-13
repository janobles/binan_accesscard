---
name: code-review
description: Use when a branch is ready for review or merge, when running CodeRabbit on a diff, when triaging review findings, or when opening a GitHub issue to park findings. Covers the CodeRabbit CLI loop, the triage posture, the repo's GitHub issue format, and the branch hygiene that has to happen before a PR.
---

# Reviewing a branch

CodeRabbit is the primary reviewer for this repo. Config lives in
`.coderabbit.yaml`, which is committed.

**The GitHub App is not installed.** Reviews run through the local CLI, and you
triage off its output rather than off the pull request page. `.coderabbit.yaml`
still applies, because the CLI reads it.

## Before you branch

Local `main` lags merged pull requests. Fetch and reset before branching or
before checking whether something merged:

```bash
git fetch origin
git checkout main && git reset --hard origin/main
```

Skipping this has cost a redone branch before.

## Before you open a PR

```bash
composer lint
vendor/bin/phpunit
```

CI runs both on every pull request to `main`, so a red lint blocks the merge, not
just the review.

**Never run `composer lint:fix` across the repository.** `lint:format` and
`lint:fix` exist but are not in `composer lint` and not in CI. The repo is
deliberately unformatted: a whole-repo reformat produces a diff nobody can review
and moves executable tokens. `docs/reference/comment-standard.md` has the
reasoning.

For a branch claiming to touch only comments or docs, prove it rather than
asserting it:

```bash
php scripts/assert-tokens-unchanged.php <base-ref>
bash scripts/assert-css-unchanged.sh <base-ref>
```

## Running the review

```bash
coderabbit auth status                              # confirm signed in first
coderabbit review --base <base-branch> --agent      # structured findings
coderabbit review --base <base-branch> --plain      # human-readable
coderabbit review findings                          # re-print the last review
```

Run it in the background and wait. Large diffs take minutes.

Notes:
- Retry on `TRPCClientError`. It is transient.
- `coderabbit auth login` needs an interactive terminal for OAuth. If the shell
  reports a non-interactive environment, ask the user to run it in a real
  terminal, or pass `--api-key`.
- Copilot rejects diffs over roughly 20,000 changed lines with no inline
  comments. Do not wait on it for a large branch.

## Triage

**Do not blind-apply findings.** Verify each one against the code and against the
hard rules in `AGENTS.md`. A finding that contradicts a documented design
decision is a won't-fix, and you note it as one with the reason.

Recurring false positives in this repo, so you do not re-litigate them:

- `h-100` is a plain Bootstrap sizing utility used by the house style, not an SB
  Admin Pro demo class. The Pro-only markers actually banned here are
  `border-left-*` and `text-xs text-uppercase`.
- Keyword search being a substring `LIKE` is a decision, not an oversight.
  Indexes cannot help it. Do not accept a FULLTEXT suggestion.
- The absence of `declare(strict_types=1)` is deliberate and matches the CI4
  appstarter. Do not add it to one file in passing.

Then:

1. Fix the in-scope genuine bugs.
2. Re-run `vendor/bin/phpunit`.
3. Park the rest, meaning anything pre-existing or out of scope, in a GitHub
   issue citing the PR number and branch as a receipt.

## The GitHub issue format

- The title states the actual current scope.
- One scope line near the top: `**Scope:** PR # · branch · base · tool`.
- One checkbox style throughout:
  `- [ ] 🔴 Critical: \`path:line\` - description.`
- Five severities, each as emoji plus word, then a colon, then `path:line`, then
  an em dash before the description: 🔴 Critical, 🟠 Major, 🟡 Minor, ⚪ Cleanup,
  🔵 UX/needs-decision.
- Fixed items become `[x]` plus `*(Fixed: ...)*`.
- Reference-only material, meaning already-fixed or won't-fix, goes in a
  collapsed `<details><summary>...</summary>` block.
- Check for an existing issue on the topic before opening a new one, and fold
  into it with a body edit rather than duplicating. Prefer editing the body over
  adding comments.
- Close with `gh issue close`, not a comment saying "closing".

The em dash in that description format is a deliberate exception to the repo's
banned-patterns rule. It is a fixed external format, not prose.

## Findings that are really mess

A finding about pre-existing mess at a path belongs in
`docs/reference/violations.md`, the canonical punch-list, as well as or instead
of an issue. Verify it first, then append. When you fix a listed item, tick it
`[x]` and add `*(Fixed: ...)*`.
