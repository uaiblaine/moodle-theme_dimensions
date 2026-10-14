# Drop the scheduled branch from the classifier: a viewer enrolled from a later date falls into the
# instance loop, and an open self instance beside the dated row reads OPEN.
s/\} else if \(\$mine && \$mine->scheduled\) \{/} else if (false) {/;
