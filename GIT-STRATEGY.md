# GIT-STRATEGY.md

## Branches
- `main` — production. Always deployable. Protected: no direct pushes, PR + CI green required.
- `develop` — integration branch. Phases merge here first.
- `feat/<phase>-<slug>` — one branch per phase or sub-step, e.g. `feat/2-database-schema`.
- `fix/<slug>` — bug fixes outside the phase flow.
- `security/<slug>` — security-only patches, reviewed with priority.

Merge direction: `feat/*` / `security/*` → `develop` → `main` (tagged `phase-N-done` on merge).

## Commit convention
[Conventional Commits](https://www.conventionalcommits.org/), plus one project-specific type:

| Type | Use for |
|---|---|
| `feat` | New feature or phase deliverable |
| `fix` | Bug fix |
| `chore` | Tooling, config, deps, CI |
| `docs` | Documentation only |
| `refactor` | Code change that isn't a feature or fix |
| `test` | Adding/adjusting tests only |
| `perf` | Performance improvement |
| `security` | Security-specific fix or hardening (custom type — always call these out explicitly rather than burying them in `fix`) |

Format: `<type>(<scope>): <summary>` — e.g. `feat(booking): add Redis slot-hold on checkout`.
Scope is usually the phase topic or module (`booking`, `auth`, `pos`, `cms`, ...).

Each phase ends with a tag: `git tag phase-N-done`.

## Pull request checklist
Every PR into `develop` or `main` must confirm:

**Correctness**
- [ ] Builds/tests pass locally and in CI (Pint, ESLint, tsc, Pest)
- [ ] No `// TODO: implement later` or stubbed logic
- [ ] Migrations are reversible (`down()` implemented) where applicable

**Security (Brief §5) — check every item that applies to this PR's scope**
- [ ] Every new/changed model has explicit `$fillable` (never `$guarded = []`)
- [ ] Every new controller action calls `authorize()` via a Policy
- [ ] Every new route is validated through a FormRequest
- [ ] Queries scoped to the authenticated user/tenant where relevant (IDOR check)
- [ ] No secret, key, or token is exposed to the client bundle
- [ ] New public-facing forms have rate limiting + honeypot/Turnstile
- [ ] File uploads (if any) go through the secure pipeline (MIME sniff, re-encode, private disk)
- [ ] No raw HTML rendered without server-side sanitization
- [ ] No PII written to logs
- [ ] `composer audit` / `npm audit` clean, or new advisories are logged in State file §10

**Process**
- [ ] `02-PROJECT-STATE.md` updated in full (not partially) if this PR closes a phase
- [ ] Commit messages follow the convention above
- [ ] Reviewer has read the diff, not just the description
