# Ignore the switch in the renderer: the custom login form is drawn even when the site
# asked for Boost Union's page.
s/        if \(!login::page_enabled\(\)\) \{\n            return parent::render_login\(\$form\);\n        \}\n\n//;
