# Drop core's hidden-course rule from the copy: a filtered profile names hidden courses to everybody.
s/&& !\$mycourse->visible\n/\&\& false\n/;
