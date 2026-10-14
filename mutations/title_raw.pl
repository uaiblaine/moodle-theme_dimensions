# Escape the node title. tool_muloginas renders its whole "Log in as" button and passes it
# as the title, so escaping prints the markup as text - the exact regression this caused.
s/\{\{\{title\}\}\}/{{title}}/g;
