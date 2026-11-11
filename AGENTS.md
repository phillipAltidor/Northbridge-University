# Project continuity

Read `PROJECT_HANDOFF.md` before starting work in this project. It records the
owner's requirements, approved decisions, current implementation, database state,
verification results, and unfinished work.

The owner explicitly requested that this handoff stay updated as work progresses.
Update it after every meaningful project change, decision, test result, migration,
or newly discovered blocker, and before ending a work session. Read-only inspection
that does not change our understanding does not need a separate entry.

Keep the handoff accurate and useful to the next agent:

- Update the current state and remaining-work list instead of merely appending
  progress claims. Clearly distinguish planned, implemented, tested, and deployed.
- Record which candidate and database a result applies to. Never present an old
  passing check as approval of a newer candidate.
- Preserve the owner's standing authorizations and constraints. Do not ask again
  for decisions already made unless a new conflict or material ambiguity arises.
- Record newly completed work in the short recent-updates section, with the date.
- Never include credentials, password hashes, individual student histories, or
  other private records. Keep sensitive artifacts in the documented private paths.
- Do not mark work complete when required checks or migration steps remain.

The handoff is maintained during project work; this instruction does not create
a background process or an automatic file watcher.
