# Decide the expand affordance's course half from the RAW course count, which is what core does: a category whose
# only course is unlisted keeps a live arrow opening onto an empty box, and says a course is there.
s/\$withcourses = \(\$count \?\? \$this->discoverable_courses_count\(\$coursecat\)\) > 0;/\$withcourses = (bool) \$coursecat->get_courses_count();/;
