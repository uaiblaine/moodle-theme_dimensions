# Read core's session cache again: it holds core's narrower answer.
s/        \$found = \[\];\n/        \$cached = \\cache::make('core', 'calendar_categories')->get('site');\n        if (\$cached !== false) {\n            return \$cached;\n        }\n        \$found = [];\n/;
