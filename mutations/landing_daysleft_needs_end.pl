# A current step with no end counts days left to the epoch.
s/\} else if \(\$name === progress::CURRENT && \$step->end > 0\) \{/} else if (\$name === progress::CURRENT) {/;
