# Ask the visitor's service for the selected day's month grid in the week view.
s/        const days = spanDays\(span, firstday\);\n/        const days = gridWeeks(monthOfSpan(span, select), firstday).flat();\n/;
