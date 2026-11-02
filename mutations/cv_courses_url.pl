# Lead a visitor to the course page, which asks for a login.
s/            'url' => links::page\(\$courseid\)->out\(false\),/            'url' => (new \\core\\url('\/course\/view.php', ['id' => \$courseid]))->out(false),/;
