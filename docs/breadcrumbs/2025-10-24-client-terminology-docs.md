# Breadcrumb
- **Task asked:** Capture the shift from “Dog” to “Client” terminology and document a mandatory post-build admin form QA sweep for future agents.
- **Plan:** 1) Touch the core guidance docs (SPEC, AGENTS, workflow, QA toolbelt) to embed the new language and QA expectations. 2) Ensure references to dogs clarify upcoming migrations without breaking current schema. 3) Leave clear instructions for happy-path form testing after every install.
- **Files changed:** SPEC.md; AGENTS.md; docs/workflow.md; QA_TOOLBELT.md.
- **Commands executed:** sed/rg to audit wording (manual review); no build commands.
- **Tests & results:** Documentation-only change — reviewed rendered Markdown to confirm formatting.
- **Tips & Tricks:** When terminology pivots, add guidance near the top of `SPEC.md` so every future sprint inherits the note automatically.
- **Remaining work:** Schedule the actual schema/REST renaming from `dogs` → `clients` in an upcoming sprint; continue enforcing the create/edit form regression pass on every build.
