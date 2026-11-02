# Lead a visitor to core's category page, which asks for a login.
s/'url' => links::category_page\(\$categoryid\)->out\(false\),/'url' => (new \\core\\url('\/course\/index.php', ['categoryid' => \$categoryid]))->out(false),/;
