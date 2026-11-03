# A step whose end passed is never late.
s/\} else if \(\$step->end > 0 && \$step->end < \$now\) \{/} else if (false) {/;
