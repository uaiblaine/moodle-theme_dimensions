# The self enrolment hint reads the instances again instead of taking the guest hint's read: one more statement per staff page.
s/(private static function add_self_enrol_hint\(.*?)\$instances \?\?= enrol_get_instances/${1}\$instances = enrol_get_instances/s;
