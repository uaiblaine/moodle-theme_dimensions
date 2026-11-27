# The enrolment row is read from the type alone: a row that classifies as none counts as no row.
s{self::staff_of\(\$courseid, isset\(\$enrolments\[\$courseid\]\)\)}{self::staff_of(\$courseid, isset(\$enrolments[\$courseid]) && \$type !== access::RELATIONSHIP_NONE)};
