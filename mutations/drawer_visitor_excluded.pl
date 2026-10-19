# Drop the account clause: a visitor and the guest account would be offered the drawer of a logged-in account.
s/\n[^\n]*if \(!isloggedin\(\) \|\| isguestuser\(\)\) \{\n[^\n]*return false;\n[^\n]*\}//;
