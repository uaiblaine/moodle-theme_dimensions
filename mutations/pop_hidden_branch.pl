# Stop a failed category from clamping its descendants: a visible grandchild under a hidden
# child is admitted on its own row, which is exactly the leak core's own recursive listing has.
s/if \(isset\(\$corefailed\[\$ancestor\]\)\) \{/if (false) {/;
