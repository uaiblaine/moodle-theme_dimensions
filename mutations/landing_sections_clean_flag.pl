# The public sections would be read again without the forced cleaning.
s/landingfields::values_many\(\[\$courseid\], true, self::SECTION_SHORTNAMES, true\)/landingfields::values_many([\$courseid], true, self::SECTION_SHORTNAMES, false)/;
