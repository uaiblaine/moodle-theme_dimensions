# Draw the favourite star on every card, the visitor's included: a control that writes a per-user row
# then reaches a page with no user, and the client module has nothing sane to do with it.
s/\$showstar = !\$result->anonymous && isloggedin\(\) && !isguestuser\(\);/\$showstar = true;/;
