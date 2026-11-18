# Read every course as a course: an event's or a trail's card draws the course's glyph.
s/return self::KINDS\[landingfields::model\(\$courseid\)\] \?\? self::KIND_DEFAULT;/return self::KIND_DEFAULT;/;
