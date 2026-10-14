# Ignore autoenrol's "new enrolments" switch: an instance taking nobody reads open.
s/\(\(int\) \$instance->customint4 === 0\)/(false)/;
