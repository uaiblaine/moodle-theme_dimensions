# Drop the guard that keeps a missing registration plugin from taking the login page
# down: the theme calls the provider whether or not it exists.
s/        if \(!class_exists\(\$provider\) \|\| !method_exists\(\$provider, 'logo_url'\)\) \{\n            return '';\n        \}\n\n//;
