# Serve whatever file the hero area holds: an inline .html from it is then same-origin script.
s/\n[ \t]*if \(!\$file->is_valid_image\(\)\) \{\n[ \t]*return null;\n[ \t]*\}(?=\n)//;
