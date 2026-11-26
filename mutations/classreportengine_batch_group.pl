# A batch's second call that asks for another group is answered from the first call's report.
s/\|\| \$report->requested_group\(\) !== \$groupid/|| false/;
