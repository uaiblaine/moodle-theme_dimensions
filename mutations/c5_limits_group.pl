# Give a group's event its course's limits, which core does not.
s/if \(\$courseid && \$courseid !== \(int\) SITEID && empty\(\$event->get_group\(\)\)\) \{/if (\$courseid \&\& \$courseid !== (int) SITEID) {/;
