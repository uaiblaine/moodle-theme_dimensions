# Drop the crests from the web service's allowlist: clean_returnvalue() removes what is not
# declared, so the server-rendered first page carries them and every page the client appends does
# not - on one listing, with nothing on screen or in a log saying so.
s/\n[ \t]*'badges' => new external_multiple_structure\(\n[ \t]*new external_single_structure\(\[\n[ \t]*'url' => new external_value\(PARAM_URL, 'The badge image, through the theme\\'s own file route\.'\),\n[ \t]*'alt' => new external_value\(PARAM_RAW, 'Its alternative text: the file name, as the editor named it\.'\),\n[ \t]*\]\),\n[ \t]*'The course\\'s own landing page badges, in grid order; empty for a course with none\.'\n[ \t]*\),//;
