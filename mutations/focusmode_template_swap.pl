# Drop the line that swaps in the reduced shell. The predicate still answers, the layout
# context is still built, and Boost Union's own drawers template renders instead - so every
# test of the predicate alone stays green and only a test which renders the real layout and
# looks for the bar can see it.
s/\n[ \t]*\$templatename = 'theme_dimensions\/focusmode';(?=\n)//;
