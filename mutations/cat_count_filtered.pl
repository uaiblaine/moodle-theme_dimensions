# Print the RAW course count beside the category name in COUNT mode: "(2)" for a category holding one listed
# course and one unlisted one, which tells an outsider the unlisted course exists.
s/\$html = \$this->restate_courses_count\(\$html, \$count\);/\$html = \$this->restate_courses_count(\$html, \$coursecat->get_courses_count());/;
