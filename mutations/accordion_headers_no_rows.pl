# Ask the database for each panel's number instead of rolling up the tally the page already holds -
# the cheapest shape of "fetch every panel's rows on the server". One statement per child, so a
# category with forty children pays forty round trips on its first paint, and the budget that says
# the accordion costs at most one statement in either mode stops holding.
s/'count' => \$totals\[\$id\],/'count' => (int) \$GLOBALS['DB']->count_records('course', ['category' => \$id]),/;
