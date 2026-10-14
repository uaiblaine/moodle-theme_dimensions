# Put the category back into the tag feed's details line, which is the branch of tagged_courses()
# that never reaches coursecat_courses() at all: with the exclusive mode off, core builds a
# core_tag\output\tagfeed itself and names the category of every course it lists.
s/\$showcat = \$showcategories && \$course->category\n                && category_access::is_category_discoverable\(\(int\) \$course->category\);/\$showcat = \$showcategories;/;
