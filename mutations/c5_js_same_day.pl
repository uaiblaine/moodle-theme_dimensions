# Ask core to move an event to the day it starts on.
s/if \(day === move\.from\) \{/if (day === move.from \&\& !day) {/;
