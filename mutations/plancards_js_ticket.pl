# Let every answer write, so an older refill can overwrite a newer one.
s/\n[^\n]*if \(ticket !== state\.ticket\) \{\n[^\n]*return;\n[^\n]*\}//;
