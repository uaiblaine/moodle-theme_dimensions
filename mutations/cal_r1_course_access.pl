# Name the category of a course the viewer may not open.
s/        if \(!\$course \|\| !can_access_course\(\$course\)\) \{/        if (!\$course) {/;
