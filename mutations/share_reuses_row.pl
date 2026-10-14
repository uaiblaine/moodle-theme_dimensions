# Forget that a course already has a code, so every visit to the course form mints another
# one and the address printed yesterday is not the address shown today.
s/        return \$row \? \(string\) \$row->shortcode : null;/        return null;/;
