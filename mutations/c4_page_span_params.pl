# Spell the week view's address as the month's.
s/            \? \[self::PARAM_VIEW => self::VIEW_WEEK, self::PARAM_WEEK => \$week\]\n/            ? [state::PARAM_MONTH => \$month]\n/;
