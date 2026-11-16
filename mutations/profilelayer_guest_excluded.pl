# Drop the guest clause: the guest account gets the profile layer.
s/\n[^\n]*if \(isguestuser\(\)\) \{\n[^\n]*return false;\n[^\n]*\}//;
