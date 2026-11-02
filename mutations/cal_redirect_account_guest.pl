# Count the guest as a real account.
s/isloggedin\(\) && !isguestuser\(\) \?/isloggedin() ?/;
