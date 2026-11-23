# Renew the events' token for any calendar event, a user's own included.
s/if \(\(int\) \$event->contextlevel === CONTEXT_COURSECAT\) \{/if (true) {/;
