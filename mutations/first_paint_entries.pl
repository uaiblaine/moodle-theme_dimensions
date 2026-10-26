# Take the first paint away from the logged-in shell's listing: its first row of covers then waits for
# layout like every other cover, and nothing but this gate says so.
s/\$view, \$manageactions, \$pages, true\);/\$view, \$manageactions, \$pages);/;
