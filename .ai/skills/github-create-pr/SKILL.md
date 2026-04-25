---
name: github-create-pr
description: Create high-quality GitHub pull requests from local changes. Use when the user asks to create, draft, open, publish, or prepare a PR, including WordPress/PHP changes in this repository.
---

# GitHub Create PR

Use this skill to prepare and open a GitHub Pull Request.

## Workflow

1. Inspect the current branch and working tree.
2. Separate user changes from agent changes. Do not revert unrelated user work.
3. Summarize the diff and identify tests run.
4. Create a focused commit only when the user asked to commit or PR the changes.
5. Push the branch.
6. Open a draft PR unless the user explicitly asks for a ready-for-review PR.

## PR Body Template

```markdown
## Summary

## Testing

## Notes
```

Keep the body factual. Mention unavailable tests rather than implying coverage.

## Commands

```bash
git status --short
git diff --stat
git diff
git branch --show-current
gh repo view --json nameWithOwner
gh pr create --draft --title "..." --body-file pr.md
```

Use `gh pr view --web` only if the user wants the browser opened.

## Safety

- Do not run destructive git commands.
- Do not include secrets in commits or PR descriptions.
- Do not squash, rebase, or force-push unless the user explicitly asked for that operation.
