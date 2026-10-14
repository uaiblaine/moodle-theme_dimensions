# Render the title raw into the attribute: a course name with a quote or an
# ampersand then breaks or injects into the head.
s/content="\{\{title\}\}"/content="{{{title}}}"/;
