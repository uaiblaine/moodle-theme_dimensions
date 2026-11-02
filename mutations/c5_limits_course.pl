# Ask no course for its limits.
s/if \(\$courseid && \$courseid !== \(int\) SITEID && empty\(\$event->get_group\(\)\)\) \{/if (false) {/;
