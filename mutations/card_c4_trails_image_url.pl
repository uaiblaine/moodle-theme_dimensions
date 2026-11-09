# Drop a course's own image: an uploaded file's address becomes the empty string.
s/\$image instanceof \\moodle_url \? \$image->out\(false\)/\$image instanceof \\moodle_url ? ''/;
