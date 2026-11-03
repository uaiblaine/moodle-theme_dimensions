# The day view's address is the month's: a reload loses the day view.
s/return \[self::PARAM_VIEW => self::VIEW_DAY, self::PARAM_DAY => \$day\];/return [state::PARAM_MONTH => \$month, self::PARAM_DAY => \$day];/;
