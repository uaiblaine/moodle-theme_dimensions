# Drop the category events' cut from the visitor's answer.
s/return self::shape\(\$window, \$source->truncated\(\), \$items, \[\], /return self::shape(\$window, false, \$items, [], /;
