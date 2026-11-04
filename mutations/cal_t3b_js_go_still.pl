# Wait for the end of a scroll that a reader who asked for less motion never sees.
s/if \(Math\.abs\(left - track\.left\) < 1 \|\| still\(\)\) \{/if (Math.abs(left - track.left) < 1) {/;
