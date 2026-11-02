# Read the classic marker without remembering it: core's arrows and reload bounce back to the page.
s/\$session->\{self::SESSION_FLAG\} = \$account;/\$session->{self::SESSION_FLAG} = 0;/;
