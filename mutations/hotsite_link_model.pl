# Remove the "does this course actually have a hotsite" check, so every course offers
# a link to a page that forwards straight back to the standard enrolment page.
s/        if \(!hotsitefields::values\(\$course->id\)->model\) \{\n            return;\n        \}\n\n//;
