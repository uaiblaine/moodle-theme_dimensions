# Boost Union's guest enrolment hint is for the course home.
s/(private static function add_guest_enrol_hint\(.*?)if \(!\$notice->coursehome \|\| !\$course->visible\) \{/${1}if (!\$course->visible) {/s;
