# A batch's second call that asks for another search is answered from the first call's report.
s/\|\| \$report->search\(\) !== classreport::search\(\$search\)/|| false/;
