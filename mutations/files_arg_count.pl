# Accept any number of segments after the area: extra segments are then silently ignored.
# BOTH occurrences, which is what the trailing g is for: the line appears once in the badge
# branch and once in the branch the hero, the cover and the og area share. Without it only the
# badge's copy goes, and that one reddens NOTHING - the list destructuring then reads a missing
# key, which is a warning rather than a failure, and the comparison below it fails on null
# anyway.
s/if \(count\(\$args\) !== 2\) \{/if (false) {/g;
