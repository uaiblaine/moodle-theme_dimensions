# Fetch on "Clear period" with no period set.
s/(if \(Listing\.closestOf\(event, SELECTORS\.periodclear\)\) \{\n(?:            [^\n]*\n)*?)            if \(state\.datefrom !== \x27\x27 \|\| state\.dateto !== \x27\x27\) \{\n                setRange\(\x27\x27, \x27\x27\);\n            \}\n/${1}            setRange(\x27\x27, \x27\x27);\n/;
