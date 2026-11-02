# Refuse the guest only after the course is looked up and asked about: the refusal then costs more for a course
# that exists than for an id nothing holds, which is the lookup's order showing through.
my $guest = '';
s/(\n        if \(isguestuser\(\)\) \{\n            throw new \\moodle_exception\(guard::ERROR_COURSE, self::COMPONENT\);\n        \})/$guest = $1; ''/e;
s/(\n\n        return dialogue::content\(\$course\);)/$guest$1/;
