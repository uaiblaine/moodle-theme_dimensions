# Drop only the period's start from its chip's address: the end alone stays a period in force.
s/\[criteria::PARAM_DATEFROM, criteria::PARAM_DATETO\]\n/[criteria::PARAM_DATEFROM]\n/;
