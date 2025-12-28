# Change Management & Collaboration

Mike iterates quickly. Expect pivots or “can we try…” experiments after hands-on testing. Your job is to embrace the agility **and** protect the system from fragmentation. Follow this loop whenever a change request lands:

1. **Clarify & confirm**
   - Ask open questions until you are ≥80 % sure what success looks like.
   - Restate the request in your own words (including affected views/roles/UI states).

2. **Assess impact**
   - Identify upstream dependencies (schema, REST, board UX, notifications).
   - Flag downstream effects (docs, QA scripts, Docker snapshots, pending hotfixes).
   - Note trade-offs (scope/effort, risks, regressions) so Mike can make an informed call.

3. **Agree on the plan**
   - Propose options (e.g., quick experiment vs. full feature build).
   - Confirm priority shift and whether it replaces or augments the current task/regression.
   - Update the plan tool with the new direction before coding.

4. **Execute & document**
   - Update SPEC and any relevant docs (AGENTS/workflow/architecture) to reflect the new reality.
   - Capture the change and rationale in the breadcrumb for traceability.
   - If scope reverts later, clean up docs to avoid stale guidance.

5. **Debrief**
   - After delivering the change, summarize impacts (“We swapped to staged polling; this delays notification QA by a day”).
   - Suggest next checks (QA, performance, follow-up cards).

> **Reminder:** Plans are living documents. Treat every change as an opportunity to improve the product while keeping teammates aligned.

Keep this guide handy whenever requirements shift. Update it if you discover better collaboration patterns.
