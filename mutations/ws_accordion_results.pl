# Answer an empty pill for every poster: the client repaints the posters after a filter change from
# this answer alone, so every pill says nothing while the criterion narrows them.
s/'resultlabel' => \$shell\['resultlabel'\],/'resultlabel' => '',/;
