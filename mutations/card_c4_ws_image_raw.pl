# Declare the step's image a URL: the data URI of a course with no image would fail the answer.
s/PARAM_RAW,(\n\s*)'Its card image/PARAM_URL,$1'Its card image/;
