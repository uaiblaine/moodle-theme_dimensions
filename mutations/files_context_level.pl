# Accept any context whose instance id happens to be a public course's id.
s/if \(\$context->contextlevel != CONTEXT_COURSE\) \{/if (false) {/;
