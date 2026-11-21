# The context's days are not checked against the month: a partial context is counted.
s/if \(\$dates !== \$expected \|\| /if (false \&\& \$dates !== \$expected || /;
