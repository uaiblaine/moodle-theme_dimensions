# Answer the guest account: a shared login would be told what the last person who used it opened.
s/\n[^\n]*if \(isguestuser\(\)\) \{\n[^\n]*throw new \\moodle_exception\('noguest'\);\n[^\n]*\}//;
