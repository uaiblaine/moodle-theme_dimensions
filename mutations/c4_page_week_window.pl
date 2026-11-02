# Embed the month's grid in the week view's first paint.
s/\$answer = range::answer\(\$window, \$scope, \$state->on\(\), \$state->filters\(\)\);/\$answer = range::answer(window::from_iso(...self::grid(\$month, \$firstday)), \$scope, \$state->on(), \$state->filters());/;
