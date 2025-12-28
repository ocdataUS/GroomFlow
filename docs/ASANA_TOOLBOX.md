# ASANA_TOOLBOX — Operations Clarity / GroomFlow

Use this quick reference to work Asana tasks with the `ocasana` CLI. Always lock a task before writing code, keep **GF Status** current, and move tasks between sections instead of touching the generic **Status** field.

## Workspace & project
- Workspace: **Operations Clarity** (`1206396381753286`)
- Project: **GroomFlow** (`1212222472264357`)
- CLI profile: `--profile ocdata` (token preloaded)
- Sections: ToDo (`1212222472279794`), Doing (`1212222470604323`), Done (`1212222470855273`)
- GF Status options: Not started, In progress, Blocked, PM Review, Closed (`projects custom-fields-list --project 1212222472264357 --profile ocdata`)

## Intake ritual (lock before coding)
1. List candidates: `ocasana tasks list --section 1212222472279794 --profile ocdata --json`
2. Read details/comments: `ocasana tasks show --task <id> --profile ocdata --json` + `ocasana tasks comments-list --task <id> --profile ocdata`
3. **Lock it:** `ocasana tasks comment --task <id> --text "LOCKED by Codex agent YYYY-MM-DD – starting <slice>." --profile ocdata`
4. **Set GF Status → In progress** and move to Doing:
   ```bash
   ocasana tasks update --task <id> --field "GF Status=In progress" --project 1212222472264357 --profile ocdata
   ocasana tasks move --task <id> --section 1212222470604323 --profile ocdata
   ```

## During the slice
- Progress/keep-alive: comment with what changed + artifact paths.
- Blocked: comment the blocker, set `GF Status=Blocked`, keep task in **Doing** until resolved.
- Handoff for PM review: comment with QA artifacts + summary, set `GF Status=PM Review`. Move to **Done** only when PM marks **GF Status=Closed**.
- Dependencies/dates/priorities: update links, due dates, and any priority field when discovered (`ocasana tasks depend` / `tasks update --due_on` / `tasks update --field "Priority=<value>"`).

## Handy commands
```bash
# Show tasks in Doing with GF Status
ocasana tasks list --section 1212222470604323 --profile ocdata --json

# Post final artifact summary
ocasana tasks comment --task <id> --text "QA: phpcs → /opt/qa/artifacts/phpcs-123.txt; smoke → /opt/qa/artifacts/qa-smoke-...txt" --profile ocdata

# Switch GF Status values quickly
ocasana tasks update --task <id> --field "GF Status=PM Review" --project 1212222472264357 --profile ocdata
ocasana tasks update --task <id> --field "GF Status=Closed" --project 1212222472264357 --profile ocdata
```

Keep Asana in sync with breadcrumbs: every code slice should have a lock comment, GF Status update, section move, and a final artifact summary.
