# Boost Union's self enrolment hint is for a visible course.
s/(private static function add_self_enrol_hint\(.*?)if \(!\$notice->coursehome \|\| !\$course->visible\) \{/${1}if (!\$notice->coursehome) {/s;
