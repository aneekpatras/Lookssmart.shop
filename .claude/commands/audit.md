---
description: Security and quality audit of the codebase against Brief §5
---

Audit only. Do not build features.

1. Take every control from `00-PROJECT-BRIEF.md` §5 (Security Baseline) and find it in the codebase.
2. For each control, give a row: **Control | ✅/🟡/❌ | File:line (evidence) | What's missing**.
3. Extra checks:
   - Any model without an explicit `$fillable`?
   - Any model without a Policy? Any controller without an `authorize()` call?
   - Any query that could read another user's data without scoping (IDOR)?
   - Any user input not going through a FormRequest?
   - Any secret or API key in the code or shipped to the frontend?
   - Where are the N+1 queries?
   - Run `composer audit` and `npm audit --audit-level=high`.
4. Sort findings by severity (Critical → High → Medium → Low), each with a concrete fix.
5. Update `02-PROJECT-STATE.md` §9 (Security Checklist) and §10 (Known Issues).
6. Do not fix anything yet — show me the findings first and I'll tell you what to fix.

$ARGUMENTS
