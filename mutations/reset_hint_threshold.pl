# Push the hint past any number of wrong passwords a person would type: it never shows.
s/return \$failures >= self::RESET_HINT_AFTER;/return \$failures >= 99;/;
