# A viewer who reads learners only through per-user grants is given the class report.
s/\n\s+&& classreport::may_view\(context_course::instance\(\$courseid\)\);/;/;
