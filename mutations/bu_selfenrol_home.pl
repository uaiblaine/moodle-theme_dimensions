# Boost Union's self enrolment hint is for the course home.
s/(private static function add_self_enrol_hint\(.*?)if \(!\$notice->coursehome \|\| !\$course->visible\) \{/${1}if (!\$course->visible) {/s;
