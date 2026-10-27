# Append the calendar component whatever the caller asked for: the visitor's shell, which names no
# component, would append a node naming none, and the AMD calendar would never mount on either shell.
# The pattern is the CONDITION and nothing under it, which is this file's rule for a gate.
s/    if \(component !== ''\) \{/    if (true) {/;
