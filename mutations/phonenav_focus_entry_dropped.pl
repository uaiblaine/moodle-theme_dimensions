# Never list the focus mode entry in the phone user menu, wherever focus mode is available.
s/if \(focusmode::available\(\$page\)\) \{\n(\s+\$entries\[\] = self::entry\(\n\s+self::FOCUS)/if (false) {\n$1/;
