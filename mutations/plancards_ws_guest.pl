# Answer the guest account: a shared login would be read the plans of whoever core lets the guest see.
s/\n[^\n]*if \(isguestuser\(\)\) \{\n[^\n]*throw new \\moodle_exception\('noguest'\);\n[^\n]*\}//;
