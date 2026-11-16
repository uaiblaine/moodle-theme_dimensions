# Take the title from fullname() instead of identity: the chosen field and its mask never reach the layer.
s/'title' => identity::title\(\$USER\),/'title' => fullname(\$USER),/;
