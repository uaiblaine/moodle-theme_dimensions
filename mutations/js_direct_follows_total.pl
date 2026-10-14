# Keep "Courses in" on screen whatever the answer's total. A search matching only an area's course
# then leaves the section and its skip link standing over nothing.
s/const none = Number\(response\.total\) === 0;/const none = false;/;
