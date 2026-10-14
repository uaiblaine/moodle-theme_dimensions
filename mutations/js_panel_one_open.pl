# Leave the previous poster open when another area opens. The panel moves to the new area while
# the old poster keeps its open ring, says "Hide courses" and reports aria-expanded="true" for a
# panel that is no longer its own - two areas claiming the one panel. The sweep exercises
# categorypanel_source_test's assertion, not a browser.
s/setPosterOpen\(prev, false\);/setPosterOpen(prev, true);/;
