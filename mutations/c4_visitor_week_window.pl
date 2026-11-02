# Embed the month's grid in the visitor's week view.
s/\$answer = publicrange::answer\(\$window, \$on\);/\$answer = publicrange::answer(window::from_iso(...self::grid(\$month, \$firstday)), \$on);/;
