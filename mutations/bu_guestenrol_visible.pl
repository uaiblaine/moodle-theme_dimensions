# Boost Union's guest enrolment hint is for a visible course.
s/(private static function add_guest_enrol_hint\(.*?)if \(!\$notice->landing \|\| !\$course->visible\) \{/${1}if (!\$notice->landing) {/s;
