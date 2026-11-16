# Drop the logged-in clause: a visitor who is not logged in gets the profile layer.
s/\n[^\n]*if \(!isloggedin\(\)\) \{\n[^\n]*return false;\n[^\n]*\}//;
