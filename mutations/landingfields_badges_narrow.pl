# Read the badges through values(), which formats every field of the course, instead of the narrow read.
s/return array_values\(self::pictures\(\$courseid, self::CREST_SHORTNAMES\)\);/return array_values(self::values(\$courseid)->crests);/;
