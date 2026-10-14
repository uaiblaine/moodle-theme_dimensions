# Let enrol_coursecompleted fall through to the fallback widget, which names the prerequisite to anybody.
s/\n\s*case 'coursecompleted':\n.*?\n\s*\$completed = true;\n\s*break;\n//s;
