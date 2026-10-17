# Take the start day in UTC instead of the site's timezone, which the period's bounds are days of.
s/criteria::iso\(\$start\)/gmdate('Y-m-d', \$start)/;
