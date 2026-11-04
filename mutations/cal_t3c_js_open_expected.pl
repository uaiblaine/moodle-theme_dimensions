# Take a course with an expected completion date for open too.
s/const open = start !== null && end === null;/const open = start !== null \&\& (end === null || expected);/;
