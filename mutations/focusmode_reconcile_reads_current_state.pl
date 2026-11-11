# Decide from the stale request instead of the current state: never treat field and setting as agreeing, so a setting flipped back before cron still gets the old answer written and the default re-set.
s/\$current === \$target\)/\$current === 2)/;
