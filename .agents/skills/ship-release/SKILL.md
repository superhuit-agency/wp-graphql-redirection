---
name: ship-release
description: Create a new versioned release (ship commit + git tag) for the wp-graphql-redirection plugin. Determines the correct semver bump from commits since the last ship commit, updates all version files and the changelog, stages the files, commits with the ship format, and creates a git tag. Use when user asks to ship, release, create a ship commit, or bump the version.
---

# Ship Release

## Quick start

1. Find the last release boundary (latest semver tag, fallback to strict ship commit)
2. Inspect commits since then (subject + body) to determine semver bump
3. Update all version files
4. Commit and tag

## Semver rules

| Commit types present | Bump |
|---|---|
| `feat` | minor |
| `fix`, `chore`, `refactor`, `perf`, `docs`, `test`, `ci` only | patch |
| Any `BREAKING CHANGE` in body | major |

`BREAKING CHANGE` has priority over commit type and must be detected in full commit bodies/footers, not only subjects.

## Workflow

### 1. Determine new version

```sh
# Find latest semver tag as release boundary (preferred)
LAST_TAG=$(git tag --list 'v*.*.*' --sort=-v:refname | head -1)

# Fallback only when no release tag exists yet
if [ -z "$LAST_TAG" ]; then
  LAST_SHIP_SHA=$(git log --grep='^🚀 ship: v' --format='%H' -n 1)
  RANGE="${LAST_SHIP_SHA}..HEAD"
else
  RANGE="${LAST_TAG}..HEAD"
fi

# List commit subjects since boundary
git log --oneline "$RANGE"

# Detect BREAKING CHANGE in full commit messages (subject + body + footer)
git log --format='%H%n%s%n%b%n----' "$RANGE" | grep -n "BREAKING CHANGE"

# Read current version
grep "Version:" wp-graphql-redirection.php
```

Apply semver bump to get `NEW_VERSION`.

Important: Do not use `grep "ship"` on commit subjects to find the last release commit. It can match unrelated commits (for example, commits mentioning `ship-release`) and produce a wrong range.

### 2. Update files

Update these files — all occurrences are the plugin's own version, not deps:

**`wp-graphql-redirection.php`** — two places:
- ` * Version: X.Y.Z` in the file header
- `define('WP_GRAPHQL_REDIRECTION_VERSION', 'X.Y.Z');`

**`readme.txt`** — two places:
- `Stable tag: X.Y.Z` in the header block
- Add a new changelog entry under `== Changelog ==`:
  ```
  = NEW_VERSION =
  * <summary of changes from commits>
  ```

### 3. Commit and tag

```sh
git add wp-graphql-redirection.php readme.txt
git commit -m "🚀 ship: vNEW_VERSION"
git tag vNEW_VERSION
```

## Changelog entry format

- One bullet per logical change (not per commit)
- Present tense, imperative mood
- Group related fix + chore into one bullet if they address the same concern
- Source wording from commit subjects, but rephrase for end-user clarity
