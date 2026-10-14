# Keep a course whose category's listing the reader may not view and which they are not enrolled in.
s/if \(\\core_course_category::can_view_course_info\(\$course\) \|\| is_enrolled\(\$context, \$USER, '', true\)\) \{/if (true) {/;
