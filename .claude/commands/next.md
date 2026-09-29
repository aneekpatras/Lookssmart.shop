---
description: Pick up the next pending phase and run the 7-step LOOP from CLAUDE.md
---

Follow THE LOOP in `CLAUDE.md`:

1. Read `02-PROJECT-STATE.md`. Is anything unfinished in §4 (In Progress)? Finish that first.
   Otherwise take the first `⬜` phase from the §2 Phase Tracker.
2. Verify against the disk (`ls`, read key files) — if the state file and the disk disagree,
   the disk is the truth; correct the state file.
3. Pull that phase's full spec from `01-PHASE-PROMPTS.md`, plus the relevant sections of
   `00-PROJECT-BRIEF.md` (especially §5 Security).
4. Give me the plan (what gets built + the full path of every file + packages + risks) and
   **stop for approval**.
5. Once approved: write complete code, verify it, rewrite `02-PROJECT-STATE.md` in full, and commit.
6. Give me a summary and stop.

$ARGUMENTS
