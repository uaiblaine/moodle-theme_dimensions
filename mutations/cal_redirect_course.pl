# Send a course calendar to a page that has no course scope.
s/        if \(\(int\) \(\$request\[.course.\] \?\? SITEID\) !== \(int\) SITEID\) \{/        if (false) {/;
