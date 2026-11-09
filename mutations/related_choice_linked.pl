# The form would offer, and the submission store, competencies the course is not linked to.
s/WHERE c\.id IN \(SELECT cc\.competencyid FROM \{competency_coursecomp\} cc WHERE cc\.courseid \{\$insql\}\)/WHERE c.id > 0/;
