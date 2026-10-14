# Accept any context whose instance id matches: a course context under the category's id then reaches
# the category's image through the course's own context.
s/if \(\$context->contextlevel != CONTEXT_COURSECAT\) \{/if (false) {/;
