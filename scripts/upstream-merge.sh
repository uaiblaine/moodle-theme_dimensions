#!/usr/bin/env bash
# Theme Dimensions - bring the copies of core and Boost Union code back to parity with today's upstream.
#
# Every copy keeps a snapshot of the upstream it was last re-diffed against (classes/local/upstream.php). This
# script reads today's upstream from a stack (scripts/upstream.php, run inside it) and, for each snapshot that
# changed:
#   - a template copy: applies the upstream change to the copy with `git merge-file` (a three-way merge whose
#     base is the snapshot), then refreshes the snapshot. Conflicts are left in the copy with markers.
#   - a method copy: prints the upstream diff, to apply by hand, then refreshes the snapshot.
#   - a copy with no snapshot yet: writes the snapshot only; check that the copy really tracks it.
# With --check it changes nothing and prints the upstream diffs.
#
# Usage: scripts/upstream-merge.sh [--check] [stack]   (default stack m503; it must mount this checkout)
# Exit status: 0 when nothing is left to do by hand, 1 otherwise.
set -euo pipefail

check=0
if [[ "${1:-}" == "--check" ]]; then
    check=1
    shift
fi
stack="${1:-m503}"
container="${stack}-webserver-1"
root="$(cd "$(dirname "$0")/.." && pwd)"
remote="/tmp/theme_dimensions-upstream"
local_dir="$(mktemp -d)"
trap 'rm -rf "$local_dir"' EXIT

docker exec "$container" rm -rf "$remote"
docker exec "$container" php /var/www/html/public/theme/dimensions/scripts/upstream.php --export="$remote" \
    > "$local_dir/list"
docker cp -q "$container:$remote/." "$local_dir/upstream"

todo=0
while IFS=$'\t' read -r snapshot copy source; do
    new="$local_dir/upstream/$snapshot"
    old="$root/$snapshot"
    if [[ -f "$old" ]] && cmp -s "$old" "$new"; then
        continue
    fi
    if [[ ! -f "$old" ]]; then
        echo "new snapshot: $snapshot (upstream $source) - check that $copy tracks it"
        if [[ "$check" -eq 0 ]]; then
            cp "$new" "$old"
        fi
        todo=1
        continue
    fi
    echo "upstream changed: $copy (upstream $source)"
    if [[ "$check" -eq 1 ]]; then
        diff -u --label "snapshot" --label "today" "$old" "$new" || true
        todo=1
        continue
    fi
    if [[ "$copy" == *.mustache ]]; then
        conflicts=0
        git merge-file -L "$copy" -L "snapshot" -L "today" "$root/$copy" "$old" "$new" || conflicts=$?
        if [[ "$conflicts" -ne 0 ]]; then
            echo "  $conflicts conflict(s) left in $copy"
            todo=1
        else
            echo "  merged cleanly into $copy"
        fi
    else
        diff -u --label "snapshot" --label "today" "$old" "$new" || true
        echo "  apply this to the method in $copy by hand"
        todo=1
    fi
    cp "$new" "$old"
done < "$local_dir/list"

exit "$todo"
