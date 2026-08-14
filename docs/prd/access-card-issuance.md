# Access card issuance

Written 2026-08-14. Nothing here is built yet.

The city is replacing the paper QR prints with real printed access cards. The
cards will be designed, produced in house, and issued once to every family, and
they are meant to last years.

That reissue is the only chance to change what a card carries. Once cards are in
people's hands, the payload format is fixed for the life of the card, and any
change to it means printing the whole city again. This document is about getting
that format right, and about one property the current scheme cannot provide at
all: a scanner being able to tell a city-issued card from a QR code somebody
generated themselves.

## The problem, stated plainly

Today the QR encodes a control number, and the control number is the family
head's `memberID` with no padding and no ceiling
(`app/Libraries/Qr/ControlNumber.php`). `parse()` accepts any string of digits.
`QrCardSettings::$controlNumberWidth` defaults to 1, so the printed number is the
bare id.

Two consequences follow, and both matter more once the card is a durable object
rather than a paper slip.

**Card numbers are sequential and dense.** A person holding card 4210 can infer
that 4211 and 4209 exist and belong to other families. Guessing a valid number
takes no skill and no information.

**A guessed number is indistinguishable from a printed card.** The scanner
receives a string, parses it as an integer, and resolves it through `qr_control`.
Nothing in that path can say where the string came from. A QR code produced by a
free website, encoding a number a person guessed, scans exactly like the card the
city printed and logs a handout against a real family.

Nothing has gone wrong yet because the paper prints were treated as informal. A
printed card is not informal. It looks official, it will be trusted by staff, and
it will be in circulation long enough for someone to work out how simple it is.

## Two things that are currently one thing

The design rests on separating a distinction the current schema does not make.

**The control number is the family's permanent identity.** It is the primary key
of `qr_control`, it maps one to one to a family head, and every handout ever
logged carries it in `subsidy_distribution.control_no`. It is history. It must
never change, and it must never be reused for a different family. If number 4211
belongs to one household this year and another next year, every handout recorded
against 4211 silently reattributes itself, and nothing in the database records
that a swap happened. There is no second source to reconstruct the truth from.

**The card token is what the QR encodes.** It is a random value with no meaning,
attached to one printing of one card. It is disposable on purpose.

Today those are the same value, which is why a lost card cannot be cancelled: the
only way to kill the code on it is to kill the family's identity. Splitting them
is what makes reissue, revocation, and unguessability possible at the same time,
and none of the three is reachable without the split.

## What a card has to prove

A scan has to answer three separate questions, and they fail differently:

| Question | Answered by | What a failure means |
|---|---|---|
| Did the city issue this? | The MAC in the payload | Fabricated or corrupted code |
| Is this card current? | The token existing in `qr_control` | Card was reissued, or never printed |
| Which family is this? | `control_no` reached through the token | Nothing, this is the normal path |

Keeping them separate matters for the message the operator sees. "This is not a
city card" and "this card was replaced" are different situations with different
responses at the desk, and collapsing them into one rejection makes both
unactionable.

## Decisions taken

These are settled. They are recorded here because the reasoning is what a design
session will need, and because reversing any of them after printing is a citywide
reissue.

### The control number stays, unchanged and immutable

It stays the human-readable label printed on the face of the card, it stays the
key that handouts reference, and it keeps its current form. It is never recycled,
never reassigned, and never changed for a family that already has one. Whether it
becomes zero-padded for print is a cosmetic question left open below.

### The QR encodes a random per-card token, not the control number

Each card carries a token generated from a cryptographically secure random
source. Guessing one is not feasible, and knowing one tells an attacker nothing
about any other. Because the token is stored against the family rather than
derived from it, reprinting issues a new token and the old card stops scanning
immediately, which is card revocation the current design cannot express.

The token is unguessable because of its randomness, not because it is secret in
transit. It is printed in a QR on a card that people carry, so it is public in the
sense that anyone holding the card can read it. That is fine, and it is the same
security property a door key has.

### The payload also carries a keyed MAC

The token alone proves the code matches a roster entry. It does not prove the
city produced the card. A message authentication code over the token, keyed with
a secret held by the system, adds that proof, and it is checkable before any
database work happens.

This is deliberately included from the start rather than added when a need for it
appears. A card printed without a MAC can never gain one. Adding the field later
means either reprinting every card in the city, or accepting a permanently mixed
population where some cards verify and some do not, which is the exact condition
the one-time reissue exists to avoid.

The MAC is not encryption, and encryption is not what is wanted here. Encryption
hides content from a reader, and there is nothing to hide: the control number is
printed on the face of the card in plain text. What is wanted is proof of origin,
which is what a MAC gives.

### The payload carries a key identifier

The secret behind the MAC will eventually need rotating, and a scheme where
rotation invalidates every printed card is a scheme nobody will ever rotate. A
short key identifier in the payload lets the system verify against retired keys
while signing new cards with the current one, so rotation becomes an ordinary
operation instead of a citywide reprint.

### The payload declares its kind, and the format reserves room to grow

Two different changes can arrive later, and they need different accommodation.

**A field gets appended.** The format has to be parseable in a way that tolerates
an extra field without invalidating cards already printed.

**A whole second kind of payload appears.** A cryptographic contactless card does
not present a static string at all, so it cannot be verified by the same
procedure. See the carrier section below. The payload therefore begins with a
marker saying what kind it is, and verification dispatches on that marker. A card
kind that did not exist when the first card was printed can then be added, and
both kinds work side by side indefinitely with no reissue of either.

This is the one part of the whole design that cannot be fixed after the fact.

Two further constraints shape the encoding:

**Stay inside the QR alphanumeric character set.** Uppercase letters, digits, and
a handful of punctuation including the full stop encode in QR alphanumeric mode,
which fits noticeably more characters into the same size code than byte mode. A
lowercase letter anywhere in the payload forces byte mode for the whole thing and
makes the printed code denser at the same physical size, which matters on a card
face and matters more for a scanner reading a worn card.

**Keep it short.** Payload length drives QR version, which drives module size at a
fixed print area, which drives how reliably a card scans after a year in a
wallet.

### Verification order is MAC, then roster, then family

Check the MAC first, because it needs no database and rejects garbage cheaply.
Then resolve the token. Then load the family. Each stage has its own rejection
message.

### Issuing and reissuing a card writes an audit row

Hard rule 3 covers family mutations, and a card issuance is one: it changes what
object can claim on behalf of that family. A reissue in particular has to be
traceable, because it invalidates a card that may still be in someone's hands.
The existing reprint path already writes an audit row (`docs/14-access-cards.md`),
and this extends rather than replaces that behaviour.

## What the card is made of

The physical carrier is not decided, and it does not have to be. Almost every
option carries the same string, so the design stays the same and the plastic
changes. The exception is worth understanding, because it is the one that has a
purchasing deadline attached.

| Carrier | What it gives | Why it is or is not a candidate |
|---|---|---|
| QR printed on the card | Anything a camera or a wedge scanner reads, no per-lane hardware | The default. Already works with the guns the city has |
| Data Matrix | Same, denser at small sizes, better with scratches | Real option if the card face is crowded. Print decision, not a security one |
| PDF417 | Holds more data, needs width | No benefit here. The payload is short by design |
| 1D barcode | A number and nothing else | No room for a MAC. Rules itself out |
| Magnetic stripe | Encoder and reader per lane | Wears out, clones with cheap hardware. No |
| Contact smart card | A secure element, through a reader slot | Pads break, insertion is slower than presenting, and a queue of several hundred feels every second |
| MIFARE Classic | Contactless | Its crypto is publicly broken. No |
| NTAG 213, 215, 216 | Contactless memory | The chip recites what was written to it, and blank tags let a UID be copied. Exactly as cloneable as a QR, at the price of a reader per lane |
| NTAG 424 DNA, DESFire EV2 or EV3 | AES in the chip, a fresh authenticated message per tap with a counter | The only family that resists cloning. Needs card stock and readers |
| UHF RFID | Reads at several metres | Reads eight cards at once in a queue. Wrong tool for one at a time claiming |
| Security printing | Holograms, UV ink, microtext | Works on the human inspection path. Cheap, and pairs with any of the above |

Every row above except the cryptographic contactless one carries a static
string. Swapping between them changes the printer and possibly the reader, and
changes nothing in the verification path.

The cryptographic contactless family is different in kind. Those chips do not
hold a payload to be read, they compute a different one on every tap, so
verifying one is a separate procedure rather than a variation on parsing fields.
That is why the payload carries a kind marker from the first card printed. It is
also why the choice of card stock has a deadline: **plain PVC cannot become a
chip card later.** If cryptographic contactless is ever wanted, that decision has
to happen before stock is bought, and it is a procurement question rather than a
software one.

Recommendation: design for the payload, stay agnostic about the plastic. Print
QR on whatever stock the budget allows, build the verification path around the
kind marker, and if a chip card is funded later the same system verifies both
kinds while the old cards age out.

## What this does not fix

**Cloning.** Anyone who photographs a genuine card can reprint the QR, and no
optical code can prevent that. What contains it is not the payload:

- One handout per family per batch, already enforced through
  `SubsidyDistributionModel::inBatch()` (`docs/15-distribution.md`), caps the
  damage at one extra claim.
- The head's name and barangay printed on the face let desk staff match the card
  to the person and an ID.
- The photo, when biodata exists, is the real control on a card being handed to
  somebody else.

Anything stronger requires the card itself to compute a fresh response per scan,
which no printed code can do and a cryptographic contactless chip can. See the
carrier section.

**A leaked database copy.** Tokens live in the database, and database copies
already travel to venues on laptops (`docs/prd/temp-aid-patching.md`). A stolen
copy exposes every token, and a copy of the environment file exposes the MAC key.
That is a deployment and disk-encryption problem, and it should be written into
the deployment plan rather than solved in the payload format.

**A card issued to the wrong household.** If the spreadsheet maps a family to the
wrong control number, the card is authentic and wrong. Import validation is where
that is caught.

## Goals

1. A QR code the city did not produce is rejected at the scanner, with a message
   that says so, before it reaches the family lookup.
2. A card can be cancelled and replaced without touching the family's identity or
   any handout ever recorded against it.
3. The payload format can gain a field later without reissuing a single card.
4. A rejected scan tells the operator which of the three failure kinds happened.
5. Every card issued or reissued is traceable to the user who did it and the time
   it happened.

## Non-goals

- Preventing a genuine card from being photocopied.
- Preventing a genuine card from being lent to a neighbour. That is the photo,
  and the photo is not in this scope.
- Hiding the control number. It is printed on the card.
- Changing how a family is identified in the database. The control number is
  staying exactly as it is.
- Retrofitting anything onto the paper prints. They are being replaced.

## Requirements

### R1. The control number is immutable and never recycled

No code path changes `qr_control.control_no` for an existing row, and no code
path assigns a control number that has ever been used. A card reissue does not
touch it.

### R2. Every card carries a token unique across all cards ever issued

Uniqueness is enforced by the database, not by the generator checking first. A
collision has to fail the insert rather than silently overwrite a family's card.
Retired tokens stay reserved, so a token is never reissued to a different family
even after the card carrying it is dead.

### R3. A reissue invalidates the previous card immediately

After a reissue, the previous token no longer resolves to the family, and a scan
of the old card is rejected as a replaced card rather than as an unknown one. The
history of which tokens a family has held is retained, because a family
presenting a dead card at a desk is a question staff will have to answer.

### R4. The MAC is verified before any database access

A payload that fails MAC verification is rejected without a query. The rejection
distinguishes a malformed payload from a well-formed one whose MAC does not
verify, because the first is usually a damaged card and the second is not.

### R5. Key rotation does not invalidate printed cards

The system verifies against the key identified in the payload, from a set of keys
it holds, and signs new cards with the current one. Retiring a key is a decision
about whether to keep accepting cards signed with it, made deliberately, not a
side effect of generating a new one.

### R6. The scanner's rejection kinds are distinct in the interface

Not a city card, replaced card, unknown card, and unrecognised card kind read
differently at the kiosk and are recorded differently, because they lead to
different actions at the desk.

### R7. Issuance and reissue are audited

One audit row per card issued or reissued, naming the family, the user, and
whether it was a first issue or a replacement.

### R8. Printing a card and generating its token are one operation

A token that exists without a printed card, or a printed card whose token was
never stored, are both states the system should be unable to reach. The existing
`card_generated_at` and `card_generated_by` stamps on `qr_control` are the model,
and this extends them.

### R9. The card face carries the human-readable control number

A QR that will not read has to have a fallback, and the fallback is an operator
typing the number. That path resolves the family directly, and it deliberately
skips the MAC, because there is no MAC to type. Which means manual entry is a
weaker path than a scan, and it needs its own decision about who is allowed to
use it and whether it is recorded differently.

### R10. Verification dispatches on the payload kind

The code that verifies a scan reads the kind marker first and hands off to the
procedure for that kind. Adding a kind is adding a procedure, not editing the
existing one. A payload whose kind is unknown to this build is rejected as an
unrecognised card, which is a fourth rejection kind and reads as an out of date
scanner rather than a fake card.

### R11. Schema changes are patch files

A new column on `qr_control`, and any table holding retired tokens, is
`sql/patches/vNN-*.sql` folded into a new dump. Never a migration
(`docs/02-database.md`).

## Prerequisites

**The consolidated spreadsheet is imported and the temporary log is patched.**
This work comes after `docs/prd/temp-aid-patching.md`. Cards cannot be issued to
families that are not in the database, and reissuing cards in the middle of a
reconciliation would change the control numbers the reconciliation is matching
against.

**The card design exists.** Physical dimensions, the print area available to the
code, and what else is on the face all constrain the payload length. A format
chosen without knowing the print area can produce a code that scans on a screen
and fails on stock. The card stock itself does not have to be chosen, only its
face, with the one exception noted in the open questions.

**A decision on the deployment shape.** The current expectation is a LAN with one
local server holding the database and scanner clients reaching it over a switch,
with no internet at the venue. That shape means the roster is always current at
scan time. A different shape, where scanners hold their own copies, changes what
a stale roster means and makes the MAC load-bearing rather than defence in depth.

## Open questions

Most of this feature is future work and much of it will change. Grouping the
questions by what actually blocks each one keeps a changing answer from stalling
the parts that do not depend on it.

### Not blocked by anything

These can be settled in a design session today, and no later answer disturbs
them. They are the bulk of the feature.

1. **What are the exact field separators, the kind marker, and the growth rule?**
   Whatever is chosen has to let a future field be appended, let a future card
   kind be added, and let today's cards keep verifying through both. This is the
   one irreversible decision in the document, and none of the unknowns below
   change it.

2. **Where does the MAC key live, and who can read it?** The environment file is
   the obvious answer and matches how the rest of the system is configured. It
   also means the key sits on a machine that travels to venues, which is the
   argument for keeping it on the office server and having the venue server hold
   only what it needs to verify.

3. **Is manual control number entry allowed at the kiosk at all?** It bypasses
   the MAC by construction. Allowing it keeps a broken card usable, and it is
   also the exact hole an attacker would use. Restricting it to a supervisor, or
   flagging the handouts it produces, are the middle options.

4. **What does a family do with a dead card?** A reissued card means the old one
   is in circulation and will be presented. The desk needs a script, and the
   kiosk needs to say something more useful than a rejection.

5. **Are cards reissued in bulk, or one at a time?** A lost card is one at a
   time. A change of format, a printing error, or a barangay's worth of cards
   damaged in storage is bulk, and bulk reissue interacts with R3 in a way that
   one at a time does not.

6. **What happens to a family whose card was never printed?** A `qr_control` row
   with `card_generated_at` still null is a normal state today. With tokens, it is
   a family who cannot be scanned at all, and a distribution will surface them at
   the worst possible moment.

### Waiting on the card design

7. **How long is the token, and how is it encoded?** Length is a trade between
   guess resistance and code density. It cannot be settled without knowing the
   print area, and it is safe to leave open, because it is a number inside a
   format the previous group already fixed.

8. **Does the printed control number get a fixed width?** Zero-padding to a fixed
   width reads better on a card and makes a truncated number obvious.
   `QrCardSettings::$controlNumberWidth` already supports it, and `parse()`
   ignores leading zeros, so this is a print decision rather than a data one. The
   number space is not actually capped at six digits today, and anything printed
   should not imply that it is.

9. **Which printed code, QR or Data Matrix?** A print-quality choice driven by
   the space left on the face and how the cards are expected to wear. Neither is
   more secure than the other and the payload is identical, so this can be
   decided as late as the first print run.

### Waiting on procurement, and one of them has a deadline

10. **What card stock gets bought?** This is the only unknown in the document
    with a point of no return. Plain PVC cannot become a chip card later, so if
    cryptographic contactless is ever wanted, the decision has to be made before
    stock is purchased, not before software is written. Every other question here
    can be answered late. This one cannot be answered again.

11. **Are per-lane readers in the budget?** A printed code needs no reader the
    city does not already have. Any contactless card needs one per scanning lane,
    and the cheap contactless options buy no security over a printed code, so a
    half-funded contactless rollout is worse than QR on both counts.

### Its own document

12. **Biodata: the photo and the signature specimen.** They change what a card
    is for, making it an identity document rather than a claim token, and they
    need capture, storage, and a consent posture that nothing in this system has
    today. The recommendation is a separate PRD, with this one reserving the space
    on the card face and nothing more.

### Sequencing, not a question

Cards cannot be issued to families that do not exist yet, and control numbers
must stop moving before anything is printed against them. Both come from
`docs/prd/temp-aid-patching.md`. That is a dependency with a known answer, so it
is recorded under prerequisites rather than here.

## Success criteria

The work is done when all of these are true:

- A QR code generated by a third party, encoding any value, is rejected at the
  kiosk and logs nothing.
- A card reissued to a family stops scanning the moment the new card is issued,
  and the family's handout history is unchanged.
- The MAC key can be rotated and every card printed before the rotation still
  scans.
- A payload from a card printed on day one still verifies after a field is added
  to the format, and after a second card kind is added alongside it.
- Every card in circulation traces to an audit row naming who issued it.
- The kiosk distinguishes a fabricated code, a replaced card, an unknown card,
  and a card kind it does not recognise, in what it shows and what it records.
