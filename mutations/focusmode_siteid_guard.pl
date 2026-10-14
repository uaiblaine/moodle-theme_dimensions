# Drop the site course clause. A front page activity renders incourse with a module context
# exactly like any other, so it starts being focused - in a course which has no ordinary
# settings form to host the switch that would turn it off.
s/\n[ \t]*if \(\$courseid === \(int\) SITEID\) \{\n[ \t]*return 'siteid';\n[ \t]*\}\n/\n/;
