# Serve whatever reached a badge area without asking core whether it is an
# image. A same-origin .html written through the file API is then script for everyone who may see
# the category, and the hero area beside it still refuses the very same bytes. (A well-formed SVG
# passes the check anyway, so the exemption admits nothing a browser can draw.)
s/            if \(!\$badge->is_valid_image\(\)\) \{\n[ \t]*return null;\n[ \t]*\}\n//;
