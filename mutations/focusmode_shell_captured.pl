# Drop the line that reads Boost Union's layout for focus mode. The predicate still answers and the
# body class still goes on, but no bar is printed at the top of the body and no foot under the content -
# a focused page with no way out, which only a test that renders the real layout and looks for the bar sees.
s/\n[ \t]*\$this->focusshell = \\theme_dimensions\\local\\focusmode::shell\(\$context, \$this->page, \$this\);(?=\n)//;
