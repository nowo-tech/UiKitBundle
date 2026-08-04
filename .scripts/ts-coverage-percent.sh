#!/usr/bin/env sh
set -eu

RAW_FILE="${1:-coverage-ts.txt}"

if [ ! -f "$RAW_FILE" ]; then
  echo "ERROR: coverage output file not found: $RAW_FILE" >&2
  exit 1
fi

# Align with vitest.config.ts / docs/COVERAGE.md:
# lines, statements, functions ≥ 90%; branches ≥ 85%.
MIN_MAIN="${TS_COVERAGE_MIN:-90}"
MIN_BRANCHES="${TS_COVERAGE_BRANCHES_MIN:-85}"

VALUES="$(
  sed 's/\x1B\[[0-9;]*[A-Za-z]//g' "$RAW_FILE" \
    | awk '
      /^[[:space:]]*Statements[[:space:]]*:/ {
        for (i=1; i<=NF; i++) if ($i ~ /^[0-9]+(\.[0-9]+)?%$/) { gsub(/%/, "", $i); statements=$i; break }
      }
      /^[[:space:]]*Branches[[:space:]]*:/ {
        for (i=1; i<=NF; i++) if ($i ~ /^[0-9]+(\.[0-9]+)?%$/) { gsub(/%/, "", $i); branches=$i; break }
      }
      /^[[:space:]]*Functions[[:space:]]*:/ {
        for (i=1; i<=NF; i++) if ($i ~ /^[0-9]+(\.[0-9]+)?%$/) { gsub(/%/, "", $i); functions=$i; break }
      }
      /^[[:space:]]*Lines[[:space:]]*:/ {
        for (i=1; i<=NF; i++) if ($i ~ /^[0-9]+(\.[0-9]+)?%$/) { gsub(/%/, "", $i); lines=$i; break }
      }
      END {
        if (statements=="" || branches=="" || functions=="" || lines=="") exit 1
        printf "%s %s %s %s", statements, branches, functions, lines
      }
    '
)"

if [ -z "${VALUES:-}" ]; then
  echo "ERROR: Could not extract TS coverage summary from ${RAW_FILE}" >&2
  exit 1
fi

set -- $VALUES
STATEMENTS=$1
BRANCHES=$2
FUNCTIONS=$3
LINES=$4

echo "TS coverage: statements ${STATEMENTS}% | branches ${BRANCHES}% | functions ${FUNCTIONS}% | lines ${LINES}%"
echo "Thresholds: statements/functions/lines ≥ ${MIN_MAIN}%; branches ≥ ${MIN_BRANCHES}%"

FAIL=0
for metric_value_min in \
  "statements ${STATEMENTS} ${MIN_MAIN}" \
  "functions ${FUNCTIONS} ${MIN_MAIN}" \
  "lines ${LINES} ${MIN_MAIN}" \
  "branches ${BRANCHES} ${MIN_BRANCHES}"
do
  set -- $metric_value_min
  METRIC=$1
  VALUE=$2
  MIN=$3
  if awk "BEGIN { exit !(${VALUE} < ${MIN}) }"; then
    echo "ERROR: TS ${METRIC} coverage ${VALUE}% is below minimum ${MIN}%" >&2
    FAIL=1
  fi
done

exit "${FAIL}"
