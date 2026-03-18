# ASANA_TOOLBOX — Operations Clarity / GroomFlow

Use this quick reference to work Asana tasks with the `ocasana` CLI. For GroomFlow, Asana is the live PM system for non-trivial work: pick or create one task, keep `GF Status` aligned with the section, and use the task notes/comments as the live brief.

## Workspace & project
- Workspace: **Operations Clarity** (`1206396381753286`)
- Project: **GroomFlow** (`1212222472264357`)
- CLI profile: `--profile ocdata` (token preloaded)
- Sections:
  - `Inbox` (`1212172738940524`)
  - `Ready` (`1212222472279794`)
  - `Active` (`1212222470604323`)
  - `Blocked` (`1213732788211928`)
  - `PM Review` (`1213718066198088`)
- `Closed` (`1212222470855273`)
- GF Status options: Not started, In progress, Blocked, PM Review, Closed (`projects custom-fields-list --project 1212222472264357 --profile ocdata`)

## Project Brief
The GroomFlow project brief is the human-readable operating summary for the whole project. Read it before task intake and refresh it whenever the repo-wide workflow changes.

## State Rules
- `Inbox` or `Ready` => `GF Status=Not started`
- `Active` => `GF Status=In progress`
- `Blocked` => `GF Status=Blocked`
- `PM Review` => `GF Status=PM Review`
- `Closed` => `GF Status=Closed`

Do not let the section and `GF Status` drift.

## Task Notes Template
Every non-trivial task should carry:

```text
Goal:
- The change or outcome required.

Context:
- Relevant files, docs, screenshots, links, users, or prior tasks.

Constraints:
- Standards, architecture, safety rules, environments, or tool limits.

Done When:
- The observable completion criteria.

Links:
- Commit, PR if any, artifacts, related tasks, or production references.
```

This mirrors the recommended Codex task brief structure. If the user request does not fit the currently active task, create a new task instead of stretching the old one.

## Mandatory Re-onboarding Audit
1. Check live work first:
   ```bash
   ocasana projects briefs-get --project 1212222472264357 --profile ocdata --json
   ocasana tasks list --section 1212222470604323 --profile ocdata --json   # Active
   ocasana tasks list --section 1213732788211928 --profile ocdata --json   # Blocked
   ocasana tasks list --section 1213718066198088 --profile ocdata --json   # PM Review
   ocasana tasks list --section 1212222472279794 --profile ocdata --json   # Ready
   ```
2. For the task you may continue, read everything before coding:
   ```bash
   ocasana tasks show --task <id> --profile ocdata --json
   ocasana tasks comments list --task <id> --include-system false --profile ocdata --json
   ocasana tasks attachments list --task <id> --profile ocdata --json
   mkdir -p /tmp/ocasana/<id>
   ocasana tasks attachments download --task <id> --all --dir /tmp/ocasana/<id> --profile ocdata --json
   ```
3. If no task matches the needed work, create one in GroomFlow with the task-notes template, start it in `Active`, and then lock it.
4. Record the active task ID/link in `AGENT_HANDOFF.md` before editing files.

## Intake Ritual (lock before coding)
1. Read the task notes, comments, and attachments.
2. If needed, rewrite the task notes so `Goal`, `Context`, `Constraints`, and `Done When` are explicit.
3. **Lock it:** `ocasana tasks comment --task <id> --text "LOCKED by Codex agent YYYY-MM-DD – starting <task>." --profile ocdata`
4. **Set GF Status → In progress** and move to `Active`:
   ```bash
   ocasana tasks update --task <id> --field "GF Status=In progress" --project 1212222472264357 --profile ocdata
   ocasana tasks move --task <id> --section 1212222470604323 --profile ocdata
   ```
5. If the project has no suitable open task, create one first:
   ```bash
   ocasana tasks create --project 1212222472264357 --name "Task title" --notes "Goal:\n- ...\n\nContext:\n- ...\n\nConstraints:\n- ...\n\nDone When:\n- ...\n" --field "GF Status=In progress" --profile ocdata --json
   ```

## During The Task
- Progress/keep-alive: comment with what changed + artifact paths.
- If blocked, comment the blocker and the exact next action, set `GF Status=Blocked`, and move the task to `Blocked`.
- If the task is interrupted, leave the exact next action, touched files, and blockers in an Asana comment before you stop.
- For PM review, comment with QA artifacts + summary, set `GF Status=PM Review`, and move the task to `PM Review`.
- Only move to `Closed` when the work is genuinely done or explicitly cancelled.
- Dependencies/dates/priorities: update links, due dates, and any priority field when discovered (`ocasana tasks depend` / `tasks update --due_on` / `tasks update --field "Priority=<value>"`).

## Handoff / Compaction Ritual
1. Update `AGENT_HANDOFF.md` with task ID/link, branch, `git status`, latest breadcrumb, QA artifacts, blockers, and one exact next step.
2. Refresh `docs/context/context-pack.json` with `bash scripts/generate_context_pack.sh`.
3. Post an Asana comment with the same status summary plus exact artifact paths and the breadcrumb path.
4. Set `GF Status` accurately:
   - `Blocked` if the next agent cannot proceed without input.
   - `PM Review` when QA is complete and review is needed.
   - `Closed` only when the task is actually done.
5. Move the task into the matching section. Do not leave `PM Review` or `Blocked` items sitting in `Active`.

Git policy: GroomFlow works on `main` by default. Mention a branch in the task only when the work is intentionally happening off `main`.

## Handy commands
```bash
# Show the full project
ocasana tasks list --project 1212222472264357 --profile ocdata --json
ocasana projects briefs-get --project 1212222472264357 --profile ocdata --json

# Show tasks by live section
ocasana tasks list --section 1212222470604323 --profile ocdata --json
ocasana tasks list --section 1213732788211928 --profile ocdata --json
ocasana tasks list --section 1213718066198088 --profile ocdata --json
ocasana tasks list --section 1212222470855273 --profile ocdata --json

# Post final artifact summary
ocasana tasks comment --task <id> --text "QA: phpcs → /opt/qa/artifacts/phpcs-123.txt; smoke → /opt/qa/artifacts/qa-smoke-...txt" --profile ocdata

# Switch GF Status values quickly
ocasana tasks update --task <id> --field "GF Status=Blocked" --project 1212222472264357 --profile ocdata
ocasana tasks update --task <id> --field "GF Status=PM Review" --project 1212222472264357 --profile ocdata
ocasana tasks update --task <id> --field "GF Status=Closed" --project 1212222472264357 --profile ocdata

# Refresh the project brief when the operating model changes
ocasana projects briefs-update --project 1212222472264357 --title "GroomFlow Operating Model" --text "..." --profile ocdata --json
```

Keep Asana in sync with handoff state: every real task should have clear notes, a lock comment, an accurate `GF Status`, a matching section, a final artifact summary, and a current `AGENT_HANDOFF.md`.
