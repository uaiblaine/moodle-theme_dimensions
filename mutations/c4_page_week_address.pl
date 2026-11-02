# Write the month alone into the week view's address, so a reload shows the month.
s/\$PAGE->set_url\(self::url\(self::span_params\(\$view, \$month, \$week, \$day\) \+ \$state->query\(\)\)\);/\$PAGE->set_url(self::url([state::PARAM_MONTH => \$month] + \$state->query()));/;
