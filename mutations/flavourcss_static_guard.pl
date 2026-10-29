# Compile whatever flavour is asked even when Boost Union's static already holds another: the second
# sheet of a process then carries the first flavour's look.
s/if \(\$held !== null && \$held !== \$flavourid\)/if (false)/;
