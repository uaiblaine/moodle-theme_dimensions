# Render whatever image url a provider hands over, wherever it lives - the leak the
# page exists to close.
s/        if \(\$url === '' \|\| !str_starts_with\(\$url, rtrim\(\$CFG->wwwroot, '\/'\) \. '\/'\)\) \{\n            return '';\n        \}\n\n//;
