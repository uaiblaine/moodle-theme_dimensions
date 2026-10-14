# Draw a visitor's posters under a criterion from the narrowed tree: a child the search misses then
# vanishes from the page instead of collapsing into the "nothing in N areas" summary, and a visitor's
# page under a criterion no longer shows the areas the same page shows at rest.
s/\$atrest = \$scope->anonymous && \$criteria->has_filters\(\) && self::partitions\(\$criteria\);/\$atrest = false;/;
