# Every trail course is offered: the showcase gate is not asked.
s/\$admitted = \$rows \? population::admitted\(\$root, \$rows\) : \[\];/\$admitted = \$rows;/;
