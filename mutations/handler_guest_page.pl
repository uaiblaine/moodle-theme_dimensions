# The guest entry's code resolves to the page asking for the entry.
s/        if \(\$type === links::LINKTYPE_GUEST\) \{\n            return links::guest_page\(\(int\) \$identifier\);\n        \}\n//;
