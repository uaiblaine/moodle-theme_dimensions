# Count the guest account as a reader with favourites: core keeps none for it, so the toggle is drawn
# over nothing and an address asking for them empties its page.
s/return !\$anonymous && isloggedin\(\) && !isguestuser\(\);/return !\$anonymous && isloggedin();/;
