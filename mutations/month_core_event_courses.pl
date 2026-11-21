# The course and open keys' own source is skipped: dated courses lose their dots.
s/if \(array_intersect\(\[keys::COURSE, keys::OPEN\], \$on\)\) \{\n(\s+\$eventcourses = new event_courses)/if (false) {\n$1/;
