#!/usr/bin/env bash
# Fast proof of mutation gates: apply ONE gate of gates.conf to its file, run only the test
# file(s) it is expected to redden, restore the file. Seconds per gate instead of a whole-suite
# sweep; the full sweep (mdl mutate) stays the owner's, on his machine.
#
#   bash mutations/gate-fast.sh <gate>:<test file>[,<test file>...] [<gate>:<test file> ...]
#   e.g. bash mutations/gate-fast.sh \
#        panel_results_wording:tests/output/category_listing_test.php
#
# Test files are relative to this repository. The runner is chosen by what exists:
#   - mdl  (the owner's machine): mdl phpunit <stack> theme/dimensions/<test file>,
#          stack from GATEFAST_STACK (default m502);
#   - mpci (a cloud session): mpci --reuse --only phpunit --filter <test class>, over the install
#          a first plain `mpci` (or `mpci --only phpunit`) made in /tmp/mpci/MOODLE_502_STABLE.
# GATEFAST_RUNNER=mdl|mpci forces one.
#
# A gate that stays GREEN is the finding (exit 1). The mutated file is restored on every exit,
# a kill included: an agent killed mid-proof once left a mutation in this bind-mounted checkout,
# which every stack then served.
set -u

repo=$(cd "$(dirname "$0")/.." && pwd)
spec="$repo/mutations/gates.conf"
runner=${GATEFAST_RUNNER:-}
if [ -z "$runner" ]; then
    if command -v mdl >/dev/null 2>&1; then runner=mdl; elif command -v mpci >/dev/null 2>&1; then runner=mpci; fi
fi
case "$runner" in
    mdl | mpci) ;;
    *) echo "gate-fast: neither mdl nor mpci is available (set GATEFAST_RUNNER)" >&2; exit 2 ;;
esac
stack=${GATEFAST_STACK:-m502}
mpcienv=/tmp/mpci/MOODLE_502_STABLE/mci.env
if [ "$runner" = mpci ] && [ ! -f /tmp/mpci/MOODLE_502_STABLE/installed ]; then
    echo "gate-fast: no mpci install yet - run 'mpci --only phpunit' once, then retry" >&2; exit 2
fi
if ps -Ao command | grep -E "mdl-(mutate|ci)|moodle-plugin-ci" | grep -v " grep " | grep -q "dimensions"; then
    echo "gate-fast: refusing to run - an mdl mutate sweep or mdl ci of this theme is in flight" >&2; exit 2
fi

lock="${TMPDIR:-/tmp}/gate-fast-$(basename "$repo").lock"
if ! mkdir "$lock" 2>/dev/null; then
    echo "gate-fast: another gate-fast holds $lock (remove it only if no gate-fast is running)" >&2; exit 2
fi
current=""
restore() {
    if [ -n "$current" ] && [ -f "$repo/$current.gatefast.bak" ]; then
        cp "$repo/$current.gatefast.bak" "$repo/$current" && rm -f "$repo/$current.gatefast.bak"
        if [ "$runner" = mpci ] && [ -f "$mpcienv" ]; then
            plugindir=$(grep -E '^PLUGIN_DIR=' "$mpcienv" | cut -d= -f2- | tr -d '"')
            [ -n "$plugindir" ] && cp "$repo/$current" "$plugindir/$current"
        fi
    fi
    current=""
}
trap 'restore; rmdir "$lock" 2>/dev/null' EXIT
trap 'exit 130' INT HUP TERM

export MDL_LOCK_WAIT=${MDL_LOCK_WAIT:-3600}
rc=0
for arg in "$@"; do
    tag=${arg%%:*}
    tests=${arg#*:}
    line=$(grep -E "^${tag} *\|" "$spec" | head -1)
    if [ -z "$line" ]; then echo "$tag: NOT IN gates.conf"; rc=1; continue; fi
    file=$(echo "$line" | awk -F'|' '{gsub(/ /, "", $2); print $2}')
    script=$(echo "$line" | awk -F'|' '{gsub(/ /, "", $3); print $3}')
    cp "$repo/$file" "$repo/$file.gatefast.bak"
    current=$file
    perl -0777 -i -p "$repo/mutations/$script" "$repo/$file"
    if cmp -s "$repo/$file" "$repo/$file.gatefast.bak"; then
        echo "$tag: PATTERN MATCHED NOTHING ($file)"; restore; rc=1; continue
    fi
    verdict=GREEN
    for t in $(echo "$tests" | tr ',' ' '); do
        if [ "$runner" = mdl ]; then
            out=$(mdl phpunit "$stack" "theme/dimensions/$t" 2>&1)
        else
            out=$(cd "$repo" && mpci --reuse --only phpunit --filter "$(basename "$t" .php)" 2>&1)
        fi
        summary=$(echo "$out" | grep -E '^(OK|FAILURES|ERRORS|Tests:)|mdl:|mpci:' | tail -2 | tr '\n' ' ')
        case "$summary" in *FAILURES* | *ERRORS* | *Failures:* | *Errors:*) verdict=RED ;; esac
        echo "  $tag / $(basename "$t"): $summary"
    done
    restore
    echo "$tag: $verdict (restored $file)"
    [ "$verdict" = GREEN ] && rc=1
done
exit $rc
