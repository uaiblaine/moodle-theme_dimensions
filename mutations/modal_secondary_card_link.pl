# Send the secondary button to the course page whoever is reading, instead of the card's own link.
s/'url' => \$url->out\(false\),/'url' => (new \\moodle_url('\/course\/view.php', ['id' => (int) \$this->course->id]))->out(false),/;
