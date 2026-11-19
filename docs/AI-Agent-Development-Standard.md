# AI Agent Development Standard

**Version:** 1.0  
**Status:** Baseline standard  
**Primary use:** Building complete portfolio projects with AI coding agents  
**Core principle:** Use the lightest workflow that provides sufficient confidence for the risk of the change.

---

## 1. Purpose

This standard defines a reusable, practical workflow for planning, implementing, testing, and verifying software with AI coding agents.

It is designed to:

- Reduce repeated setup and decision-making across projects.
- Give less capable agents enough explicit context to execute work reliably.
- Keep specifications, implementation tasks, and acceptance criteria clear.
- Catch defects while work is being implemented, not only at the end.
- Avoid unnecessary agents, documents, abstractions, tests, and process overhead.
- Preserve useful progress through focused Git commits and clear recovery rules.

This is a **risk-based standard**, not a requirement to run every tool or step for every change.

## 2. Non-Negotiable Principles

1. **Understand before changing:** Inspect the relevant project files, conventions, dependencies, and current Git state before implementation.
2. **Stay in scope:** Implement the requested phase or task. Do not expand scope, redesign unrelated areas, or perform opportunistic refactoring without approval.
3. **Specify observable behavior:** Requirements and acceptance criteria must describe outcomes that can be checked.
4. **Keep one source of truth:** Maintain one approved phase specification. Avoid duplicate documents that can drift apart.
5. **Test proportionately:** Choose tests based on behavior and risk. Do not add tests merely to increase test count.
6. **Verify before claiming success:** A command must be executed and its output inspected before its result can be reported as passing.
7. **Preserve working changes:** Do not overwrite, revert, stage, or commit unrelated user changes.
8. **Stop when stuck:** Do not repeat speculative fixes indefinitely. Escalate after three unsuccessful attempts to resolve the same underlying issue.
9. **Prefer simplicity:** Use existing patterns and dependencies unless a documented reason justifies a change.
10. **Human approval for material decisions:** Ask for approval when scope, architecture, security, data integrity, cost, or user-visible behavior would materially change.


## Quick Reference (Read This First)

Use this as the compact operational checklist. The risk classification in Section 3 decides which route applies.

### Small / Low Risk

```text
Inspect → Short Brief / Acceptance Criteria → Implement → Verify → Review Diff → Commit
```

### Medium

```text
Inspect → EGM Draft (when useful) → Approve Important Decisions
→ Writing Plans → Implement Task by Task + Verify → Phase Checks
→ Review Diff → Update Phase Doc → Commit
```

### Large / High Risk

```text
Inspect → EGM Brainstorm (if unclear) → EGM Draft → One Goldfish Review
→ Resolve Material Findings → Human Approval → Writing Plans
→ Controlled Execution + Task Verification → Feature / Phase Testing
→ EGM Drift Report (when justified) → Final Review → Commit / Finish Branch
```

### On Failure

```text
Reproduce → Systematic Debugging → Verify the Fix
→ After 3 Failed Attempts on the Same Issue: Stop and Escalate
```

### Universal Completion Rule

```text
No task is complete without relevant verification.
No phase is complete without its applicable completion checks.
No success claim without evidence.
```

## 3. Choose the Workflow by Risk

Classify each phase before planning. **Risk takes precedence over size.** A small code change involving authentication, authorization, payments, personal data, destructive operations, or database integrity must use the higher-risk workflow.

| Workflow | Typical work | Required process |
|---|---|---|
| **Small / Low Risk** | Static pages, styling, simple presentational components, small UI fixes | Short specification → implement → verify |
| **Medium** | Forms, API integration, state management, multi-component features, several user states | Detailed phase specification → approval of important decisions → implementation plan → implement → verify |
| **Large / High Risk** | Authentication, authorization, payments, sensitive data, database migrations, major architecture changes, critical workflows | Full design workflow → independent review → human approval → detailed implementation plan → controlled execution → full verification |

### 3.1 Classification Rules

Choose **Small / Low Risk** only when all are true:

- The expected behavior is already clear.
- The change is localized and easy to reverse.
- No security boundary, sensitive data, payment, or destructive data operation is involved.
- There are few meaningful edge cases or dependencies.

Choose **Medium** when one or more are true:

- Several components or application layers must work together.
- The feature has multiple states, validation rules, or API interactions.
- The work requires non-trivial implementation decisions.
- Regressions could affect an existing user flow, but the impact is limited and recoverable.

Choose **Large / High Risk** when one or more are true:

- Authentication, authorization, permissions, payments, secrets, or sensitive data are involved.
- Data integrity, destructive operations, migrations, or backward compatibility are involved.
- The change affects core architecture or multiple critical workflows.
- A defect could expose data, cause financial loss, corrupt data, or make the application unavailable.

When uncertain between two levels, choose the higher level only if the uncertainty is material. Do not classify a phase as high risk merely because it contains many files.

## 4. Standard Workflow

### 4.1 Small / Low-Risk Workflow

1. Inspect the relevant code and project conventions.
2. Write a concise task or phase brief containing the objective, scope, expected behavior, affected areas, and acceptance criteria. Reuse the existing roadmap or issue when it already contains this information.
3. Implement the smallest complete change.
4. Run the relevant checks and inspect the result.
5. Review the changed files and create a focused Git commit when the repository workflow permits it.

**Do not use by default:** EGM brainstorm, Goldfish review, a separate implementation-plan document, subagents, worktrees, or a drift report.

### 4.2 Medium Workflow

1. Inspect the repository and read the relevant project context and roadmap section.
2. Use EGM Draft when a structured specification will materially reduce ambiguity. Otherwise, use the phase template directly.
3. Maintain one canonical phase document. Include both:
   - **Specification:** objective, scope, behavior, states, constraints, and acceptance criteria.
   - **Implementation plan:** ordered tasks, affected files, dependencies, and task-level verification.
4. Review and approve material product or technical decisions before implementation. This does not require a separate approval meeting for routine details.
5. Use Superpowers `writing-plans` when the work needs an explicit multi-task plan. Add the result to the canonical phase document when practical; create a separate plan only when it is genuinely easier to manage.
6. Implement tasks in a controlled sequence. Verify each meaningful task before moving on.
7. Run feature-level and phase-level checks appropriate to the change.
8. Review the final diff, update the phase document if behavior or decisions changed, and create focused Git commits.

**EGM Goldfish review is optional for Medium work.** Use it only when important ambiguity, a novel design, or a high-impact decision remains.

### 4.3 Large / High-Risk Workflow

1. Inspect the repository, project context, architecture, dependencies, and current Git state.
2. Use EGM Brainstorm only when requirements or design choices are genuinely unclear. Record agreed decisions in the design artifact; do not create a concept brief when the discussion is unnecessary.
3. Use EGM Draft to create a structured, implementation-ready specification.
4. Run one EGM Goldfish review using fresh, independent reviewer context.
5. Resolve material findings. Reject suggestions that are incorrect or unnecessary, and record important decisions. Do not enter an endless review loop.
6. Obtain human approval for the scope, security-sensitive behavior, architecture, data changes, and other material decisions.
7. Use Superpowers `writing-plans` to create a detailed, ordered implementation plan. For a large phase, a separate plan file is acceptable and should link to the approved specification.
8. Execute in controlled tasks. Use `executing-plans` only when its full process is appropriate and can be followed; see Section 6. Use parallel agents only when tasks are genuinely independent and the review overhead is justified.
9. Test the critical workflows and relevant failure cases. Run the required project checks and review the final changes.
10. Use EGM Drift Report when the specification is intended to remain a living reference or when a substantial implementation could have diverged from it. Reconcile meaningful differences by updating the code or the specification, with human judgment where required.
11. Record verification evidence, remaining limitations, and final decisions.

**Review limit:** One full Goldfish review round by default. A second round is justified only when the first review exposed a material issue that could not be resolved confidently without re-review.

## 5. EGM Usage

EGM is used for design clarity, independent specification review, and (selectively) documentation-to-code drift detection. It is not a replacement for implementation tests or human approval.

### 5.1 EGM Skills / Commands

The exact invocation depends on the host application and installed EGM version.

| EGM capability | Use it for | Default |
|---|---|---|
| `egm-brainstorm` | Explore unclear requirements and compare design choices | Optional; only when needed |
| `egm-draft` | Create a structured design / phase specification | Medium when useful; Large / High Risk |
| `egm-review` | Independent, zero-context review of a specification or plan | Large / High Risk |
| `egm-resolve` | Work through material findings from a Goldfish review | Only when review findings need resolution |
| `egm-drift-report` | Compare design documents with the implementation | Large / High Risk or periodic maintenance |
| `egm-bootstrap` | Create an initial documentation map for an existing codebase | Once, only when useful |

For Claude Code and Antigravity, the documented commands use the `/egm:` prefix, for example:

```text
/egm:egm-brainstorm
/egm:egm-draft
/egm:egm-review docs/designs/feature.md
/egm:egm-drift-report docs/designs/
```

In Codex, use the corresponding installed EGM skills through the available skill interface. Do not assume that slash-command syntax is identical across hosts.

`egm-resolve` is the EGM review-resolution capability. Invoke it through the interface exposed by the installed EGM version; do not assume it is a standalone slash command on every host.

### 5.2 EGM Rules

- Give Goldfish reviewers the specification and explicitly referenced artifacts, not the author's private conversation context.
- Reviewers should assess clarity, completeness, consistency, feasibility, and alignment with the existing codebase.
- Fix material issues, not every stylistic suggestion.
- Do not treat a review score as proof that the implementation is correct.
- Do not run a drift report after every small UI task.
- Keep design documents current when implemented behavior intentionally changes.

## 6. Superpowers Usage

Superpowers provides planning, execution, testing, and debugging skills. Use the skills that fit the selected workflow; do not activate the entire framework for every task.

| Skill | Purpose | When to use |
|---|---|---|
| `writing-plans` | Turn an approved specification into ordered, actionable tasks | Medium and Large phases that need a multi-task plan |
| `executing-plans` | Execute an implementation plan under the skill's defined process | Large phases or when its full process is appropriate |
| `test-driven-development` | RED → GREEN → REFACTOR for behavior-driven changes | Non-trivial logic and testable behavior; follow stricter requirements if another active skill requires it |
| `verification-before-completion` | Require fresh evidence before claiming a task is complete | Whenever completion is being reported |
| `systematic-debugging` | Find and fix root causes rather than changing code randomly | When a defect, failed test, or unexpected behavior occurs |
| `subagent-driven-development` | Delegate tasks with separate implementer and reviewer contexts | Only for large work with independent tasks where the extra cost is justified |
| `using-git-worktrees` | Isolate work in a separate Git worktree | Parallel work, risky changes, or when required by the selected execution skill |
| `finishing-a-development-branch` | Verify and finish an isolated development branch | When a worktree / feature branch workflow has been used |

### 6.1 Important `executing-plans` Note

The current Superpowers `executing-plans` skill is not a lightweight synonym for “follow the checklist.” Its documented process includes an isolated workspace, a task ledger, test-driven task execution, verification of task outputs, and a fresh-context review of the completed branch. It may also require related worktree and branch-finishing skills.

Use it when the phase is large enough to justify that process and the environment supports it. Do not invoke it for a small task and then silently skip its required steps. For Small and ordinary Medium work, use the concise execution loop in this standard unless the installed skill or project policy requires otherwise.

### 6.2 Parallel Agents

Parallel execution is optional, not the default. Use it only when:

- Tasks have clear boundaries and minimal shared-file conflicts.
- Interfaces and dependencies are already defined.
- Each agent can work independently.
- The cost of coordinating and reviewing parallel work is lower than the expected time saved.

Do not split a simple phase into many agents or tickets.

## 7. Specification and Plan Documents

The goal is one authoritative phase artifact wherever practical, not a large collection of overlapping documents.

### 7.1 Document Strategy by Workflow

| Workflow | Recommended documentation |
|---|---|
| Small / Low Risk | Existing roadmap / issue plus a short task brief; create a phase file only if the information needs to persist |
| Medium | One phase document with separate `Specification` and `Implementation Plan` sections |
| Large / High Risk | One approved specification plus a separate implementation plan only when the plan is too large or operationally distinct to maintain in the same file |

A brainstorm / concept brief is optional. Do not create it when the requirement is already clear.

### 7.2 Minimum Phase Specification

A phase document should contain only the relevant sections below:

```markdown
# Phase: <Name>

## Objective
What outcome must be delivered?

## Scope
What is included and explicitly excluded?

## Context and Dependencies
Relevant project documents, existing code, APIs, design-system rules, and prerequisites.

## Expected Behavior
User-visible behavior, states, validation, permissions, and important edge cases.

## Technical Constraints
Existing patterns, interfaces, libraries, data contracts, and constraints that must be respected.

## Acceptance Criteria
Observable conditions that determine whether the phase is successful.

## Implementation Plan
Ordered tasks, dependencies, affected files, and expected outcomes.

## Verification
Relevant test commands, scenarios, and manual checks.

## Risks and Open Questions
Only unresolved items that could affect implementation or acceptance.
```

Do not fill irrelevant sections with boilerplate. If a decision is unknown and materially affects the result, record it as an open question rather than inventing an answer.

## 8. Testing and Verification

Testing is part of implementation, not a separate heavyweight project phase. Apply the smallest set of checks that provides appropriate confidence.

### 8.1 Task-Level Verification

After each meaningful task:

1. Run the tests and checks relevant to the changed behavior.
2. Inspect the command output; do not rely only on the exit status if warnings or skipped tests matter.
3. Review the changed files for accidental scope expansion, insecure patterns, and incomplete behavior.
4. Fix failures before marking the task complete or committing it.

### 8.2 Feature / Phase Verification

At the end of a feature or phase, select the checks appropriate to the project:

| Check | Use when |
|---|---|
| Lint / formatting | Whenever configured in the project |
| Type checking | For typed codebases such as TypeScript projects |
| Unit tests | For non-trivial logic, utilities, calculations, and validation rules |
| Component tests | For interactive components with meaningful states or behavior |
| Integration tests | Where components, services, persistence, or APIs must work together |
| End-to-end tests | For critical user journeys, such as sign-in, checkout, or creating a core record |
| Build | Before declaring a phase or release complete |
| Visual / responsive review | For UI work; inspect the target desktop and mobile layouts |
| Accessibility checks | For meaningful interactive UI and key user flows |
| Security-focused checks | For authentication, authorization, payments, sensitive data, and external inputs |

Examples for a Next.js + Laravel project may include ESLint, TypeScript checks, Vitest, React Testing Library, Playwright, PHPUnit, and Laravel Feature Tests. Use the tools already adopted by the project; do not install a new testing stack without a clear benefit.

### 8.3 Testing Rules

- Test behavior and risk, not every line of code.
- Do not require 100% coverage by default.
- Do not write unit tests for trivial presentational markup without meaningful logic.
- Prefer a focused integration or E2E test for a critical user journey over many redundant tests.
- For UI work, automated tests do not replace a visual review.
- For security-sensitive behavior, include negative tests (forbidden access, invalid input, duplicate requests, unauthorized state changes) where relevant.
- A skipped or unavailable test must be reported honestly, with the reason and residual risk.

### 8.4 Completion Evidence

When reporting completion, provide a short evidence summary:

- Checks actually executed.
- Pass / fail result for each check.
- Important scenarios manually verified.
- Any tests not run and why.
- Known limitations or remaining risks.

Never claim that a test, build, visual check, or review passed if it was not performed.

## 9. Git and Recovery Rules

### 9.1 Before Work

- Inspect `git status` before editing.
- Identify existing user changes and leave them untouched.
- Work on the current branch unless the project workflow or risk level requires a separate branch / worktree.
- Do not create a worktree for a tiny task by default.

### 9.2 Commit Checkpoints

After each meaningful task:

1. Run the required task-level verification.
2. Review the diff and ensure it contains only the intended changes.
3. Stage only the relevant files.
4. Create a focused commit when Git is in use and committing is permitted.

Do not commit failing or unverified work as completed work. Do not stage unrelated files, secrets, environment files, generated artifacts, or user changes. If the repository owner has a different commit policy, follow that policy.

### 9.3 Retry Limit

If the same underlying issue remains unresolved after **three reasonable attempts**:

1. Stop making speculative changes.
2. Summarize the observed failure and likely root cause.
3. List the attempted fixes and their outcomes.
4. Include the latest relevant error or test output.
5. State what information or decision is needed from the human.

Do not evade this limit by repeatedly making cosmetic changes to the same failed approach. A materially new diagnosis may justify a new attempt only after explaining why it is different.

## 10. Phase Completion Gate

A phase is complete only when all applicable conditions are true:

- [ ] The delivered behavior matches the approved scope and acceptance criteria.
- [ ] All planned tasks are complete, or remaining items are explicitly accepted as out of scope.
- [ ] Relevant task-level tests and checks have been run.
- [ ] Required feature / phase checks have been run and their results inspected.
- [ ] The production build succeeds, when the project has a build step.
- [ ] Critical user journeys have been verified where applicable.
- [ ] Responsive and visual behavior has been reviewed for UI work.
- [ ] Important security and permission cases have been checked for sensitive features.
- [ ] The final diff contains no accidental or unrelated changes.
- [ ] Documentation reflects intentional behavior or technical changes.
- [ ] Git checkpoints have been created where applicable and permitted.
- [ ] Known limitations and unexecuted checks are documented.

Do not require irrelevant checks. Mark an item as not applicable only when there is a clear reason.

## 11. Tool Versioning and Fallbacks

EGM, Superpowers, and host applications can change. This standard defines the workflow; the installed skill files and official repository documentation define the current tool behavior.

For each project, record the relevant toolchain details in a small file such as `docs/ai-toolchain.md` when these tools are used:

| Tool | Version / commit | Host application | Last verified |
|---|---|---|---|
| EGM | Exact installed release or commit | Codex / Claude Code / Antigravity | YYYY-MM-DD |
| Superpowers | Exact installed release or commit | Host application | YYYY-MM-DD |
| Coding agent | Model and version, if known | Host application | YYYY-MM-DD |

Rules:

- Check that a requested skill is actually installed and available before invoking it.
- Read the installed skill instructions when behavior is version-sensitive.
- Do not assume a command name or invocation syntax works across all hosts.
- Do not silently update or replace tools during a project task.
- If a skill is unavailable, use the corresponding workflow in this standard as a manual prompt / checklist and disclose the fallback.
- Do not claim that a fallback provides independent review if it uses the same context that authored the specification.

## 12. Agent Operating Instructions

At the start of a phase, the agent must:

1. Read this standard's Quick Reference and the section for the selected workflow.
2. Read the relevant project context, roadmap entry, design-system rules, and existing code patterns.
3. Inspect the current Git state.
4. Classify the phase by size and risk, explaining the classification briefly.
5. State the intended workflow and artifacts before making changes.
6. Identify material unknowns. Ask the human only when an unresolved decision affects scope, security, architecture, data integrity, or acceptance. Do not ask for information already present in the project files.
7. Stay within the approved scope.
8. Report verified results, not assumptions.

The agent must not:

- Implement during a planning-only task.
- Invent APIs, file paths, types, design tokens, or test results.
- Rewrite unrelated parts of the application.
- Add dependencies or abstractions without a concrete need.
- Continue indefinitely after repeated failures.
- Mark tasks or phases complete without the required verification evidence.

## 13. Official References

Use the installed version's documentation as the source of truth. The following repositories and documents describe the tools referenced by this standard:

- [Google Elephant-Goldfish Model (EGM)](https://github.com/google/elephant-goldfish-model)
- [EGM Getting Started](https://github.com/google/elephant-goldfish-model/blob/main/docs/getting_started.md)
- [EGM Design Drafting](https://github.com/google/elephant-goldfish-model/blob/main/docs/designs/draft.md)
- [EGM Goldfish Review Protocol and Resolution](https://github.com/google/elephant-goldfish-model/blob/main/docs/designs/review.md)
- [obra/Superpowers](https://github.com/obra/superpowers)
- [Superpowers: Writing Plans](https://github.com/obra/superpowers/tree/main/skills/writing-plans)
- [Superpowers: Executing Plans](https://github.com/obra/superpowers/tree/main/skills/executing-plans)
- [Superpowers: Verification Before Completion](https://github.com/obra/superpowers/tree/main/skills/verification-before-completion)
- [Superpowers: Systematic Debugging](https://github.com/obra/superpowers/tree/main/skills/systematic-debugging)

---

**Standard maintenance rule:** Keep this standard stable and intentionally small. Change it only when repeated project experience shows that a rule is missing, ineffective, or unnecessarily expensive. Do not add process steps based on hypothetical problems alone.
