# Drop the flip around core's own activity_navigation(). Core returns an empty string whenever
# the theme and the course format both use a course index, which on a topics course is every
# activity page - so the previous and next controls silently disappear and nothing but a
# rendered page can tell.
s/\n[ \t]*[^\n]*->theme->usescourseindex = false;(?=\n)//;
