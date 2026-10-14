# Boost Union's self enrolment hint is for the landing page.
s/(private static function add_self_enrol_hint\(.*?)if \(!\$notice->landing \|\| !\$course->visible\) \{/${1}if (!\$course->visible) {/s;
