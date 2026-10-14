# Boost Union's guest enrolment hint is for the landing page.
s/(private static function add_guest_enrol_hint\(.*?)if \(!\$notice->landing \|\| !\$course->visible\) \{/${1}if (!\$course->visible) {/s;
