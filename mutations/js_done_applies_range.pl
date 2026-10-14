# Break the guard that tells a changed period from an unchanged one, so apply() never tells the
# listing: "Done" closes the popover over a period the boxes name, and the listing never moves.
# The pattern is the CONDITION and nothing under it, which is this file's rule for a gate.
s/    if \(!changed\) \{/    if (true) {/;
