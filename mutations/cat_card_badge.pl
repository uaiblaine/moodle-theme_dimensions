# Let Boost Union's course card keep its category badge whatever the category. This is the
# presentation the theme is deployed with (courselistingpresentation = cards), where core's own
# "Category: name" line is never rendered at all - so with this clause gone the name of an
# undiscoverable category is printed on the card of a course its viewer was rescued into.
s/if \(\$content === '' \|\| !\$categoryid \|\| category_access::is_category_discoverable\(\$categoryid\)\) \{/if (true) {/;
