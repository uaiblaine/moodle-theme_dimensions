# Let the service's answer write whatever came after it, so an older request can overwrite a newer one's records.
s/(\[0\];)\n[^\n]*if \(ticket !== state\.ticket\) \{\n[^\n]*return;\n[^\n]*\}/$1/;
