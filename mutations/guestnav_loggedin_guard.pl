# Drop the logged-in clause: a real account loses its user menu to the visitor's actions.
s/\n[^\n]*if \(isloggedin\(\) && !isguestuser\(\)\) \{\n[^\n]*return false;\n[^\n]*\}//;
