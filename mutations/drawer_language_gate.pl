# Draw the language row whatever core's language menu says: a site with one language, or with the menu off,
# gets a row that opens an empty panel.
s/\n[^\n]*if \(empty\(\$menu\['items'\]\)\) \{\n[^\n]*return null;\n[^\n]*\}//;
