# Cache the branding with no crests in it: every category surface then draws none, however many a
# manager uploaded, and the editor still saves them - so the files are there and nothing shows them.
s/\n[ \t]*'badges' => \$badges,/\n            'badges' => [],/;
