# Intercept a modified press too: Ctrl-click or Shift-click opens the dialogue instead of the new tab or window asked for.
s/if \(event\.button !== 0 \|\| event\.metaKey \|\| event\.ctrlKey \|\| event\.shiftKey \|\| event\.altKey\) \{/if (false) {/;
