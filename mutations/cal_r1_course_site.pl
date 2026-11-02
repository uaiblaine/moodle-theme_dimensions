# Look the site course up as a course: its address loses the category it names.
s/        if \(\$courseid > 0 && \$courseid !== \(int\) SITEID\) \{/        if (\$courseid > 0) {/;
