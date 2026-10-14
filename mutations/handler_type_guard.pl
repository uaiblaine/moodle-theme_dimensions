# The handler answers only its own link types.
s/        if \(\$type !== links::LINKTYPE && \$type !== links::LINKTYPE_GUEST\) \{\n            return null;\n        \}\n//;
