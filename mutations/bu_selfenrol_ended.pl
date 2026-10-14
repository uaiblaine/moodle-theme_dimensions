# A self enrolment instance whose window has ended is not counted.
s/\n                \|\| \(!empty\(\$instance->enrolenddate\) && \$instance->enrolenddate <= \$now\)//;
