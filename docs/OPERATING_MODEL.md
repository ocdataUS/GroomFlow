# Operating Model — GroomFlow

This repo now runs on an Asana-first task model.

The goal is simple: make the live state easy for humans to read in Asana, keep the durable method in repo docs, and keep agent continuity strong even when work is interrupted or compacted.

## Core Principles
- Manage work as tasks, not slices. One coherent problem gets one Asana task, one main Codex thread, and usually one branch.
- Keep durable instructions in repo docs. Keep live task state in Asana. Keep only the current resume snapshot in `AGENT_HANDOFF.md`.
- Keep `AGENTS.md` short and practical. Put deeper process detail in this file and `docs/ASANA_TOOLBOX.md`.
- Ask the agent to work from four things: Goal, Context, Constraints, Done When. Put those in the Asana task notes so the task itself becomes the standing brief.
- Use breadcrumbs only when they add durable value: behaviour changes, important QA packets, non-obvious decisions, or interruption-heavy work.
- Use skills and automations only after the workflow is stable manually.

## System Of Record
- Asana project: live queue, task status, comments, attachments, due dates, and review state.
- Asana project brief: project-wide operating summary visible inside Asana for humans before they drill into tasks.
- `AGENTS.md`: entrypoint, build/test/install commands, and repo rules.
- `docs/ASANA_TOOLBOX.md`: exact Asana CLI workflow and section/status rules.
- `docs/OPERATING_MODEL.md`: canonical explanation of how tasks, handoffs, and compaction work in this repo.
- `AGENT_HANDOFF.md`: one current continuation snapshot, not a history log.
- `docs/context/context-pack.json`: generated compaction/re-onboarding snapshot.
- `qa/QA_LOG.md`: QA evidence ledger.
- `docs/breadcrumbs/`: optional task journals for major work, decisions, or significant QA.
- `docs/REFACTOR_LOG.md`: legacy archive from the old slice model. Do not use it for new work.

## Asana Model
Use sections for visible workflow lanes and `GF Status` for machine-readable state.

### Sections
- `Inbox`: raw requests, ideas, or tasks that still need clarification.
- `Ready`: clearly scoped work that is not started yet.
- `Active`: work currently being executed.
- `Blocked`: work waiting on input, access, or an external dependency.
- `PM Review`: implementation and QA are complete locally; waiting for review or direction.
- `Closed`: merged, shipped, cancelled by decision, or otherwise complete.

### GF Status Mapping
- `Inbox` or `Ready` => `GF Status=Not started`
- `Active` => `GF Status=In progress`
- `Blocked` => `GF Status=Blocked`
- `PM Review` => `GF Status=PM Review`
- `Closed` => `GF Status=Closed`

Do not let section and `GF Status` drift.

### Project Brief
Keep the GroomFlow Asana project brief current with the project-wide operating model, not the status of a specific task.

Use it for:
- the lane meanings
- the task-notes template
- the required handoff/compaction sequence
- any repo-wide workflow rule a human should see before opening a task

Do not use the project brief as a changelog. Task-level state still belongs on the task itself.

## Task Template
Every non-trivial repo task should have notes structured like this:

```text
Goal:
- What change or outcome is required?

Context:
- Relevant files, docs, links, errors, screenshots, users, or prior tasks.

Constraints:
- Standards, architecture, business rules, safety limits, environments, or tool restrictions.

Done When:
- Observable conditions for completion.
- Required QA or proof.

Links:
- Branch, PR, artifacts, related tasks, or production references.
```

If a user request does not fit the currently active Asana task, create a new task instead of stretching the old one.

## Branch And Thread Rules
- Keep one main Codex thread per Asana task.
- Fork only when work truly branches into a different problem.
- Recommended branch naming: `task/<asana-gid>-<short-slug>`.
- If a task is review-only or read-only, reuse the current branch only when that avoids unnecessary branch churn and does not blur ownership.

## Working Ritual
1. Start with Asana:
   - Read the GroomFlow project brief first.
   - Check `Active`, `Blocked`, and `PM Review`.
   - If continuing, read the task notes, comments, and attachments first.
   - If starting new work, either pull from `Ready` or create a fresh task.
2. Lock the task:
   - Move it to `Active`.
   - Set `GF Status=In progress`.
   - Add a lock comment.
3. Only use the planning tool when the task is complex enough to need it.
4. Work end-to-end:
   - inspect the codebase
   - implement
   - verify
   - document durable changes
5. Checkpoint only at meaningful boundaries:
   - substantial code change complete
   - blocker discovered
   - QA packet complete
   - ready for PM review

## Why This Fits Codex / GPT-5.4 Better
- `AGENTS.md` stays short, so the standing prompt does not turn into a bloated process manual.
- The task itself carries `Goal`, `Context`, `Constraints`, and `Done When`, which gives the agent a stable brief without forcing it to reconstruct scope from scattered comments.
- One main thread per task reduces context fragmentation and makes compaction cleaner.
- Durable process lives in repo docs; live task state lives in Asana; the handoff file carries only the current resume snapshot.
- Automation is deferred until the manual workflow is stable, which avoids locking in bad rituals too early.

## Handoff And Compaction Ritual
Before stopping, interruption, or context compaction:
1. Update the Asana task with:
   - current branch
   - latest commit if any
   - artifact paths
   - blocker or review state
   - one exact next step
2. Update `AGENT_HANDOFF.md` with the same current-state snapshot.
3. Run `bash scripts/generate_context_pack.sh`.
4. Add or update a breadcrumb only if the task changed behaviour, captured a meaningful QA packet, or needs a durable narrative for future agents.

The handoff should let a fresh agent resume from one Asana task, one branch, and one next action without replaying the full project history.

## QA Expectations
- Use the packaged ZIP in Docker for real verification.
- Keep QA evidence in `/opt/qa/artifacts` and log it in `qa/QA_LOG.md`.
- Put the QA summary on the Asana task before moving to `PM Review`.
- If QA is partial, say exactly what ran and what did not.

## What Changes From The Old Model
- No new slice planning backlog in repo docs.
- No new `docs/REFACTOR_LOG.md` entries.
- No requirement to create a breadcrumb for every task.
- No using breadcrumbs as the live PM system.
- No bundling unrelated work into one “carryover slice” because the branch happens to be open.

## Skills, Tools, And Automations
- Required external system: Asana via `ocasana`.
- Useful live context connectors should be added only when they remove a real manual loop.
- Once a workflow is stable, convert it into a skill before automating it.
- Good future automation candidates:
  - weekly recent-commit summary
  - stale `Active` / `Blocked` task audit
  - QA drift checks for long-lived branches
  - release-note drafting from recent commits and Asana review tasks

Automation is phase two. Stable manual workflow comes first.
