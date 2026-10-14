# Drop the layout anchor. Nothing else in the predicate excludes course/modedit.php: it sets a
# cm and a module context and only then forces the layout back to admin, so the activity
# settings form starts opening in focus mode.
s/\n[ \t]*if \(\$page->pagelayout !== 'incourse'\) \{\n[ \t]*return 'pagelayout';\n[ \t]*\}\n/\n/;
