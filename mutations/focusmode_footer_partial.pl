# Drop Boost Union's footer partial from the shell. It is what emits the end-of-body token
# core substitutes the whole page's JavaScript into, so the page still renders, still looks
# right in a screenshot, and arrives with no drawers, no completion toggle and no width
# control - with nothing in any log.
s/\n[ \t]*\{\{>\s*theme_boost\/footer\s*\}\}[^\n]*(?=\n)//;
