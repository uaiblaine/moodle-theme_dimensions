# Unwire the layout hook: the pure context function still works and is still tested,
# but nothing calls it, so the real page never carries the panel.
#
# The pattern is anchored on the CONDITION, which is the guard, with the call's arguments left out of it:
# a pattern spelling the bare call goes dead when the call gains an argument, and a dead pattern takes
# the rest of a sweep down at that line.
s/        if \(\$templatename === 'theme_boost\/login' && is_array\(\$context\) && login::page_enabled\(\)\) \{\n            \$context = login::layout_context\([^\n]*\);\n        \}\n\n//;
