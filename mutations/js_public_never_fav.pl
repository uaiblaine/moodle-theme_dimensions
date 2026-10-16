# Keep a hand-edited fav on the visitor's state: a filter the page has no control for, written back
# into every address the visitor's module mints.
s/(    state\.chip = '';\n)    state\.fav = false;\n(    Listing\.rootOnly\(state\);)/$1$2/;
