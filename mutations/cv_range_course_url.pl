# Name a course's own page in the visitor's dictionary.
s/                'url' => \$landing,/                'url' => (new \\core\\url('\/course\/view.php', ['id' => \$courseid]))->out(false),/;
