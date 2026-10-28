# Name the logged-in posters and panel component in the shared listing: the visitor's page would load a
# module the owner kept off the visitor's import graph until stage R4.
s/^(const LINE_DRAWN = 'data-line-drawn';\n)/$1\nexport const AREAS_COMPONENT = '\@moodle\/lms\/theme_dimensions\/areas\/Areas';\n/m;
