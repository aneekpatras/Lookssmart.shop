# START HERE — Setup + The One Prompt

## 1. Where the files go

```
D:\C Drive Data\Projects\Looks Smart Beauty Salon\
├── CLAUDE.md                 ← Claude Code reads this automatically, every session
├── START-HERE.md             ← this file — for you, not for Claude
├── 00-PROJECT-BRIEF.md
├── 01-PHASE-PROMPTS.md
├── 02-PROJECT-STATE.md
├── .claude\
│   └── commands\
│       ├── next.md           ← /next
│       ├── status.md         ← /status
│       ├── save.md           ← /save
│       └── audit.md          ← /audit
└── ... (the rest of the Phase 0 files)
```

That's the whole setup. Nothing else to configure.

---

## 2. How you actually work

Open a terminal in VS Code → run `claude` → and remember just **four commands**:

| Command | When |
|---|---|
| `/next` | To build the next phase. **This is 99% of your usage.** |
| `/status` | "Where are we right now?" — report only, builds nothing |
| `/save` | Context running low, or you're closing the session — saves everything to memory |
| `/audit` | Periodic security check (definitely after phases 4, 7, 12, 14) |

You never have to decide which phase to hand over. Claude reads `02-PROJECT-STATE.md` and works
out what comes next on its own.

---

## 3. THE ONE PROMPT — only for the first time (or at the start of a new session)

Copy the whole block below and paste it into Claude Code:

```
You are the lead engineer on this project. First, read `CLAUDE.md` in the project root — it
contains your full operating protocol (THE LOOP, hard rules, autopilot mode). Then read
`02-PROJECT-STATE.md` to see what has been built so far.

Never ask me what the project is or which phase to work on — read those two files and decide
for yourself.

Now start THE LOOP from CLAUDE.md:
1. Verify the state file against the actual disk, then tell me in 2 lines exactly where we stand.
2. Pick up the next pending phase.
3. Give me the plan for that phase — what will be built, the full path of every file, which
   packages will be installed, and which decisions need to be made — then stop and wait for my
   approval.

Rules that always apply:
- No point from Brief §5 (Security Baseline) gets skipped. Anything not feasible right now must
  be logged in State file §10 with a plan — never silently dropped.
- Write complete code. No "// TODO: implement later".
- At the end of every phase, write the full updated version of `02-PROJECT-STATE.md` — never skip this.
- Everything in English: conversation, code, comments, docs.
- Give me a separate, clean list of any commands I need to run on my own machine.
- Stop when the phase is done — do not start the next one on your own.
```

After that, all you type is `/next`.

---

## 4. If you want several phases run back to back

Type at any point: **`AUTOPILOT: ON`**

Claude will then run phases one after another without asking each time — but it will still stop if:
- a phase fails
- a decision needs your call (branding, pricing, Google/Twilio accounts)
- 3 phases are complete (a forced check-in)

To turn it off: **`AUTOPILOT: OFF`**

> Recommendation: do phases 1–2 manually (`/next`, read the plan, approve it). Once you're happy
> with the output quality, turn autopilot on. Keep the security-heavy phases (3, 4, 7, 12) manual —
> reviewing those plans is genuinely worth your time.

---

## 5. Your 60-second checklist after every phase

- [ ] Did `02-PROJECT-STATE.md` get updated? (check its "Last updated" line)
- [ ] Did you run the commands Claude handed you?
- [ ] `git log --oneline -3` — did it commit?
- [ ] Lost the thread? Ask: *"Explain what this phase built in 5 simple lines."*

---

## 6. When something goes wrong

| Problem | What to type |
|---|---|
| Output cut off mid-way | `Continue exactly where you stopped. Do not re-print files you already wrote.` |
| Getting an error | `I'm getting this error: <paste>. Find the root cause — don't guess. Give me a fix plus a regression test.` |
| Claude lost track | `/status` |
| Feels like it cut corners | `/audit` |
| Context running out | `/save` → new session → paste the prompt from Section 3 again |
| Moving to another tool (Cursor/GPT) | Copy §12 Handoff Block from `02-PROJECT-STATE.md` and attach the three `.md` files |
