# Ask for the month before whenever the days are at their start, not only on the way back to it.
s/\} else if \(moved < 0 && !track\.back\) \{/} else if (!track.back) {/;
