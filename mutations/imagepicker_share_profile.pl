# Give the share picture a crest's profile, losing the og canvas's shape and size.
s/\$profile = \$shortname === landingfields::SHORTNAME_SHAREIMAGE/\$profile = \$shortname !== landingfields::SHORTNAME_SHAREIMAGE/;
