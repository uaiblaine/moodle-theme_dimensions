# Keep an empty further line: the profile layer draws a blank line for every field the person does not fill.
s/if \(\$text === ''\) \{(\n[^\n]*continue;\n[^\n]*\}\n[^\n]*if \(\$text === \$title\))/if (false) {$1/;
