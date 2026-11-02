# Read an absent course as 0, not as core's SITEID.
s/optional_param\('course', SITEID, PARAM_INT\)/optional_param('course', 0, PARAM_INT)/;
