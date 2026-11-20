# Skip the batch's course contact step: a teacher reached through their own course is prevented.
s/if \(\$pending && \$course !== null\) \{/if (false) {/
