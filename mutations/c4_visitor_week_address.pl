# Write the month alone into the visitor's week address.
s/\$PAGE->set_url\(self::url\(self::span_params\(\$view, \$month, \$week, \$day\) \+ visitor::query\(\$on\)\)\);/\$PAGE->set_url(self::url([state::PARAM_MONTH => \$month] + visitor::query(\$on)));/;
