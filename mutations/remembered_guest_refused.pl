# Let the guest account remember: its preferences live in its session alone, so a state written
# for it is a state written for every visitor who shares that account.
s/return isloggedin\(\) && !isguestuser\(\);/return isloggedin();/;
