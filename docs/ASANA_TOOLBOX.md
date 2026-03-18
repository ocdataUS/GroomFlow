# ASANA_TOOLBOX — Operations Clarity / GroomFlow

Use this quick reference to work Asana tasks with the `ocasana` CLI. For GroomFlow, Asana is mandatory context: always inspect active tasks before writing code, keep **GF Status** current, and move tasks between sections instead of touching the generic **Status** field.

## Workspace & project
- Workspace: **Operations Clarity** (`1206396381753286`)
- Project: **GroomFlow** (`1212222472264357`)
- CLI profile: `--profile ocdata` (token preloaded)
- Sections: ToDo (`1212222472279794`), Doing (`1212222470604323`), Done (`1212222470855273`)
- GF Status options: Not started, In progress, Blocked, PM Review, Closed (`projects custom-fields-list --project 1212222472264357 --profile ocdata`)

## Mandatory Re-onboarding Audit
1. Check active work first: `ocasana tasks list --section 1212222470604323 --profile ocdata --json`
2. If `Doing` is empty, check candidates in `ToDo`: `ocasana tasks list --section 1212222472279794 --profile ocdata --json`
3. For the task you may continue, read everything before coding:
   ```bash
   ocasana tasks show --task <id> --profile ocdata --json
   ocasana tasks comments list --task <id> --include-system false --profile ocdata --json
   ocasana tasks attachments list --task <id> --profile ocdata --json
   mkdir -p /tmp/ocasana/<id>
   ocasana tasks attachments download --task <id> --all --dir /tmp/ocasana/<id> --profile ocdata --json
   ```
4. If no task matches the needed work, create one in GroomFlow, set `GF Status=In progress`, move it to `Doing`, and then lock it.
5. Record the active task ID/link in `AGENT_HANDOFF.md` before editing files.

## Intake Ritual (lock before coding)
1. Read the task details/comments/attachments.
2. **Lock it:** `ocasana tasks comment --task <id> --text "LOCKED by Codex agent YYYY-MM-DD – starting <slice>." --profile ocdata`
3. **Set GF Status → In progress** and move to Doing:
   ```bash
   ocasana tasks update --task <id> --field "GF Status=In progress" --project 1212222472264357 --profile ocdata
   ocasana tasks move --task <id> --section 1212222470604323 --profile ocdata
   ```
4. If the project has no suitable open task, create one first:
   ```bash
   ocasana tasks create --project 1212222472264357 --name "Slice title" --notes "Scope and deliverables" --field "GF Status=In progress" --profile ocdata --json
   ```

## During the slice
- Progress/keep-alive: comment with what changed + artifact paths.
- Blocked: comment the blocker, set `GF Status=Blocked`, keep task in **Doing** until resolved.
- If the slice is interrupted, leave the exact next action, touched files, and blockers in an Asana comment before you stop.
- Handoff for PM review: comment with QA artifacts + summary, set `GF Status=PM Review`. Move to **Done** only when PM marks **GF Status=Closed**.
- Dependencies/dates/priorities: update links, due dates, and any priority field when discovered (`ocasana tasks depend` / `tasks update --due_on` / `tasks update --field "Priority=<value>"`).

## Handoff / Compaction Ritual
1. Update `AGENT_HANDOFF.md` with task ID/link, branch, `git status`, latest breadcrumb, QA artifacts, blockers, and one exact next step.
2. Refresh `docs/context/context-pack.json` with `bash scripts/generate_context_pack.sh`.
3. Post an Asana comment with the same status summary plus exact artifact paths and the breadcrumb path.
4. Set `GF Status` accurately:
   - `Blocked` if the next agent cannot proceed without input.
   - `PM Review` when QA is complete and review is needed.
   - `Closed` only when the slice is actually done.
5. Keep the task in `Doing` unless it is genuinely ready for PM review or closure.

## Handy commands
```bash
# Show the full project when ToDo/Doing look empty
ocasana tasks list --project 1212222472264357 --profile ocdata --json

# Show tasks in Doing with GF Status
ocasana tasks list --section 1212222470604323 --profile ocdata --json

# Post final artifact summary
ocasana tasks comment --task <id> --text "QA: phpcs → /opt/qa/artifacts/phpcs-123.txt; smoke → /opt/qa/artifacts/qa-smoke-...txt" --profile ocdata

# Switch GF Status values quickly
ocasana tasks update --task <id> --field "GF Status=PM Review" --project 1212222472264357 --profile ocdata
ocasana tasks update --task <id> --field "GF Status=Closed" --project 1212222472264357 --profile ocdata
```

Keep Asana in sync with breadcrumbs and handoff state: every slice should have a lock comment, GF Status update, section move, a final artifact summary, and a current `AGENT_HANDOFF.md`.
