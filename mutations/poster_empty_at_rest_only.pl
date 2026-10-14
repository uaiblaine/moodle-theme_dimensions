# Make an empty poster a link under a criterion as well as at rest: a reader who searched sees a
# poster that opens nothing and says nothing matches, and its panel can no longer say so. The rule
# has one spelling, population::expandable(), which the page's posters and the web service share.
s/return \$filtered \|\| \$count > 0;/return \$count > 0;/;
