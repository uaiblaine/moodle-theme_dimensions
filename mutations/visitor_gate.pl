# Let a visitor past the one refusal, so a course that is not public is looked up and served.
s/        if \(\$visitor && !publicaccess::is_public\(\$courseid\)\) \{/        if (false) {/;
