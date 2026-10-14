# Draw the "or" whenever a provider is shown, even with the local form switched off.
s/        \$context->showseparator = \$context->hasidps && !empty\(\$context->showloginform\);\n/        \$context->showseparator = \$context->hasidps;\n/;
