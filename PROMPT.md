You are picking up the GroomFlow WordPress plugin refactor mid-stream. Start by opening AGENTS.md and follow it—no shortcuts. That means: work from `PROJECT_PLAN.md`/`docs/REFACTOR_LOG.md`, check for unfinished slices, then claim your slice, read the entire surface you are about to change (map every helper/consumer), and keep wrappers unless every call site is updated.

Before you edit, add an “In Progress” entry to docs/REFACTOR_LOG.md and note what you’re touching. When you change code, rebuild the packaged ZIP (`bash scripts/build_plugin_zip.sh`), reinstall it in Docker (`docker compose cp … ; docker compose run --rm -T wpcli wp plugin install … --force --activate`), and run the required tooling (qa-phpcs, other automation) against that ZIP. You must also run a quick manual admin happy-path walkthrough of the flow you touched (Clients, Guardians, Services, Packages, Flags, Views, Settings create+edit).

Before handing off, complete the pre-flight checklist in docs/REFACTOR_LOG.md, record automation artifacts plus the manual journey you exercised, update docs/HISTORY.md + docs/NOTES.md + PROJECT_PLAN.md (and CHANGELOG.md if structure moved), and make sure `dev` is up to date and clean. Do not declare the slice done if any checkbox is unchecked, and never push tool output into tracked files—always inspect `git diff`. If you discover a blocker or skip a required step, stop and document it instead of moving forward.

At the end of your final message, print EXACTLY one line:
READY FOR NEXT SLICE
or
BLOCKED ON THIS SLICE: <very short reason>
Print nothing after that line.
