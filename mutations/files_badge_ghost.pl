# Let the badge route serve a logged-in user the page would ghost: an unlisted course's badge
# then answers somebody who is refused its page.
s/if \(redirector::is_ghosted\(\$courseid\)\) \{/if (false) {/;
