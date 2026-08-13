# Operations and handover

Written for whoever ends up responsible for keeping this running: an IT
department taking the system over, or the next developer receiving it in a
turnover. Chapter 03 gets a development checkout going; this chapter is about the
copy that people depend on.

## What you are taking on

Three moving parts.

A **CodeIgniter 4 application**, served by a web server pointed at its `public/`
directory. Stateless apart from sessions; you can redeploy it by replacing the
files.

A **MySQL database** whose schema is defined by a SQL dump in the repository
rather than by migrations. This is the part that matters. The application is
replaceable from git; the database is not replaceable from anywhere.

A **background worker process** that drains a job queue. If it is not running,
large imports never finish, and they fail silently rather than loudly.

## Deploying

Point the web server at `public/`, never at the repository root. CodeIgniter's
entry point lives there deliberately: the rest of the application, the framework,
`.env`, and the SQL dump all sit above it. A server rooted at the repository
serves all of that to anyone who asks for it by path.

Then set up `.env`. It is not in git, so a fresh deployment has to have one
written. The fields that differ from a development setup:

| Field | Development | Production |
|---|---|---|
| `CI_ENVIRONMENT` | `development` | `production` |
| `app.baseURL` | `http://localhost:8090/` | the real URL people type |
| `database.default.username` | `root` | a dedicated account |
| `database.default.password` | empty | set |

`CI_ENVIRONMENT = production` is not cosmetic. In `development`, CodeIgniter shows
full stack traces on error, including file paths and query fragments. That is
exactly what you want while building and exactly what you do not want on a
machine other people reach.

The database account needs `SELECT`, `INSERT`, `UPDATE`, and `DELETE` on the
`accesscard` database. It does not need `DROP`, and it does not need access to
any other database.

Chapter 04 covers exposing the app beyond the local network, and the security
note there applies with more force to a production box.

## Backup and restore

**The database is the state.** The repository is replaceable; the records are
not. Back it up on a schedule you would be comfortable explaining after a disk
failure.

```bash
mysqldump -u<user> -p accesscard > accesscard-$(date +%F).sql
```

Restoring is importing that file:

```bash
mysql -u<user> -p accesscard < accesscard-2026-08-14.sql
```

`writable/` holds logs, sessions, the debug bar's output, and caches, none of
which need backing up. Two subdirectories are exceptions worth knowing about:
`writable/uploads/` and `writable/import-staging/` hold uploaded spreadsheets and
in-progress import staging data. Losing those loses any import that has been
uploaded but not yet confirmed. `writable/backups/` is not an automatic backup of
anything; do not mistake it for one.

Test a restore before you need one. A backup nobody has restored is a hypothesis.

## The worker as a service

Chapter 05 has the scripts and the installation commands. The operational points
for a deployment:

- The worker must be running, on a schedule, for imports to complete. Every
  minute is the default and is fine.
- Run it as a dedicated least-privilege account, not as SYSTEM or root. It needs
  read and write on `writable/` and `writable/uploads/`, and network access to
  MySQL. Nothing else. It parses untrusted uploaded files, which is the whole
  reason for the constraint.
- Its log is `writable/logs/queue-worker.log`. If imports stop completing, read it
  first.
- On a Windows laptop, Scheduled Tasks skip while on battery unless configured
  otherwise. The provided installer handles this; a task created by hand will
  not.

## Administering accounts

Account management lives under `accounts` and is reachable by Developer and Admin
roles. It covers creating a staff account, editing it, resetting a password, and
enabling or disabling it.

The five roles and what they reach are covered in chapters 00 and 10. The
operational rule is this one:

**Disable accounts, do not delete them.** `users.isactive` toggles between
`Enable` and `Disabled`, and the interface offers exactly that. Audit rows and
scan records reference `userID`. Deleting the user breaks the link, and an audit
trail that cannot say who made a change is not an audit trail. A disabled account
cannot log in and stays resolvable forever.

When someone leaves, disable their account the same day. When someone changes
role, edit the existing account rather than creating a second one.

## When something breaks

Start with `writable/logs/`. CodeIgniter writes a dated log file per day, and the
worker writes its own.

| Symptom | First thing to check | Chapter |
|---|---|---|
| Page loads but CSS and JavaScript 404, form posts bounce, QR codes point at the wrong host | `app.baseURL` does not match the URL being used | 04 |
| An import sits on "queued, waiting for worker" and never finishes | The worker is not running, or MySQL was down when it tried | 05 |
| A page 404s for one role but works for another | The navigation manifest has no entry for that role and page key. This is deliberate behaviour, not a bug | 10 |
| "Unknown column" or an enum rejection after a code change | Code is referencing a column or value that is not in the dump | 02 |
| A batch is not visible at the venue | A batch is open only when `closed_at` is NULL and `started_at` is not NULL | 15 |
| Login works, then every page bounces back to login | Session storage under `writable/session` is not writable by the web server user | 03 |
| Everything is slow, especially first page load | On the dev server, the single-worker default. In production, check the indexes | 23 |

If a change to the code is the suspect, chapter 22 covers running the test suite,
and `composer lint` catches documentation and comment problems before they reach
a review.

## Handing it on

Whoever takes this next needs four things: the repository, the current `.env`
values (not the file, the values), a recent database dump, and the credentials to
the machine it runs on. Everything else in this handbook is reconstructable from
the code.

Point them at chapter 00 first. The domain is the part that takes longest to
learn, and it is the part the code does not explain.
