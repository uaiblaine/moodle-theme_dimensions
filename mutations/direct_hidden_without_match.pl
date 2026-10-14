# Show the section of the direct courses under any criterion: a narrowed page then draws
# "Courses in X" over nothing while its results sit in the areas.
s/'show' => \$areas === null \|\| \$this->result->total > 0,/'show' => \$areas === null || \$this->result->total > 0 || \$this->criteria->has_filters(),/;
