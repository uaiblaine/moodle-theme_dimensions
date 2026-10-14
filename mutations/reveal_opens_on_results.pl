# Leave the reveal closed when it holds a result under a criterion: the one area matching the search
# sits behind "show more" while the count line says it is there.
s/'moreopen' => \$inmore \|\| \(\$filtered && \$results\['moreresults'\] > 0\),/'moreopen' => \$inmore,/;
