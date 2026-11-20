# Skip the batch's shared-course step: fellow participants lose each other.
s/if \(\$pending\) \{\n(\s*)foreach \(self::sharing_a_course_with_viewer/if (false) {\n$1foreach (self::sharing_a_course_with_viewer/
