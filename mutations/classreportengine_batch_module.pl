# A batch's second call that asks for another activity is answered from the first call's report.
s/\$report->moduleid\(\) !== max\(0, \$moduleid\)/false/;
