# Add the calendar row where Boost Union hides the calendar.
s/if \(in_array\(self::CALENDAR, \(array\) \(\$page->theme->removedprimarynavitems \?\? \[\]\), true\)\) \{/if (false) {/;
