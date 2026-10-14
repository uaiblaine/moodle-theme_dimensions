# Drop the term from the SQL cut's root statement: the grouped tally then counts every course of each
# area rather than its matches, in this mode alone - the two modes disagree about one address.
s/self::sql_predicate\(\$scope, \$plain\)/self::sql_predicate(\$scope, new criteria(0, ['anonymous' => \$scope->anonymous]))/;
