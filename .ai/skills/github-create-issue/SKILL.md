---
name: github-create-issue
description: Create high-quality GitHub issues from bugs, feature requests, TODOs, investigation notes, security findings, or user reports. Use when the user asks to create, draft, file, open, or prepare a GitHub Issue.
---

# GitHub Create Issue

Use this skill to turn context into an actionable GitHub Issue.

## Workflow

1. Identify the target repository from the current git remote or the user's explicit repository.
2. Gather enough facts to make the issue actionable:
   - Current behavior or problem.
   - Expected behavior or goal.
   - Reproduction steps when relevant.
   - Environment details when relevant.
   - Candidate files, logs, screenshots, or commands.
3. Draft a concise title and body.
4. If GitHub tools or `gh` are available and the user asked to create the issue, create it.
5. If creation is blocked by authentication or missing remote information, provide the ready-to-post title and body.

## Issue Body Template

```markdown
## Summary

## Steps to Reproduce

## Expected Behavior

## Actual Behavior

## Notes
```

Omit sections that do not apply. For security-sensitive issues, avoid posting exploit details or secrets publicly unless the repository's process explicitly allows it.

## Commands

```bash
git remote -v
gh repo view --json nameWithOwner
gh issue create --repo OWNER/REPO --title "..." --body-file issue.md
```

Prefer `--body-file` for multi-line issue bodies.
