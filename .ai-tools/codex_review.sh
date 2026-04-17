#!/usr/bin/env bash
# codex_review.sh — Run Codex CLI for code review, refactor suggestions, and test gaps.
#
# Usage:
#   ./codex_review.sh "instruction" file1 [file2 ...]
#
# Example:
#   ./codex_review.sh "Review for bugs and edge cases" src/auth/login.ts src/auth/guard.ts

set -euo pipefail

if ! command -v codex &>/dev/null; then
  echo "[ERROR] codex CLI not found."
  echo "  Install: npm install -g @openai/codex"
  exit 1
fi

if [ "$#" -lt 2 ]; then
  echo "Usage: $0 \"instruction\" file1 [file2 ...]"
  exit 1
fi

INSTRUCTION="$1"
shift
FILES=("$@")

# Validate files exist
for f in "${FILES[@]}"; do
  if [ ! -f "$f" ]; then
    echo "[WARN] File not found: $f (skipping)"
  fi
done

PROMPT="You are a senior software engineer doing a focused code review.

Instruction: ${INSTRUCTION}

For each file, analyze and report:
1. BUGS — actual or potential runtime errors
2. EDGE CASES — inputs or states not handled
3. REFACTOR — concrete simplifications or better patterns
4. MISSING TESTS — what test cases are absent

Format your response as:
## <filename>
### Bugs
...
### Edge Cases
...
### Refactor
...
### Missing Tests
...

Be concise and actionable. Skip sections with nothing to report."

echo "======================================"
echo " Codex Review"
echo " Files: ${FILES[*]}"
echo " Instruction: ${INSTRUCTION}"
echo "======================================"
echo ""

# Build file contents block
FILE_CONTENTS=""
for f in "${FILES[@]}"; do
  if [ -f "$f" ]; then
    FILE_CONTENTS+="
--- FILE: $f ---
$(cat "$f")
"
  fi
done

# Call codex CLI
echo "$PROMPT

$FILE_CONTENTS" | codex --no-project-doc --quiet -

echo ""
echo "[DONE] Review complete."
