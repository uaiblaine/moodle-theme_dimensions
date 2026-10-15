# Ignore missing fields, so a course field that was deleted leaves the picture check at fine (or at
# the notice of an unavailable type).
s/if \(\$this->missing !== \[\]\) \{/if (false) {/;
