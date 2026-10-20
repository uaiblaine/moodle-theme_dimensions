# Drop the new-course guard of the submission hook, so saving an existing course writes the default
# into every field it never answered.
s/if \(!\$hook->isnewcourse\) \{/if (false) {/;
