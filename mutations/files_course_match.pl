# Trust the course id in the path: a URL can then pair one course's context with another's id.
s/if \(\$courseid !== \(int\) \$context->instanceid\) \{/if (false) {/;
