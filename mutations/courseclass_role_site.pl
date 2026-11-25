# Leave every course's figures stale after a role or a capability changes in a category or the system.
s/(            \} else if \(\$level === CONTEXT_SYSTEM \|\| \$level === CONTEXT_COURSECAT\) \{\n)                self::bump\(self::SITE\);\n/$1/;
