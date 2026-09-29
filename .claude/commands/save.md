---
description: Save all current progress into 02-PROJECT-STATE.md (before context runs out)
---

Stop working and save memory. Do not write any new code.

1. Take stock of everything built or changed in this session
   (`git status`, `git diff --stat`, read the files).
2. Rewrite `02-PROJECT-STATE.md` **in full** (not a partial edit) — refresh the entire STEP 6 list
   from CLAUDE.md: header, §2 tracker, §3 completed, §4 in-progress (exactly where you stopped —
   file + function + line), §6 decisions, §7 database, §8 env vars, §9 security checklist,
   §10 known issues, §11 commands, §12 handoff block, §13 session log.
3. Be **extremely specific** in §4 and §12 — another AI tool (or a fresh session) must be able to
   resume from exactly that point without asking a single question. Nothing vague like "work in progress".
4. If there is uncommitted work, commit it: `wip(phase-N): <what is unfinished>`.
5. Tell me in 3 lines: what was saved, where you stopped, and what the first task is next session.
