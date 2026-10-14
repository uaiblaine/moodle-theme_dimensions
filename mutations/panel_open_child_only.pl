# Trust the address's open: a panel is drawn for whatever id it names, whether or not that id is an
# expandable poster of the page being drawn. A grandchild or a category of another tree then opens
# a panel on a page that has no poster for it, and the parameter answers differently for ids that
# exist and ids that do not - an oracle, where the design says an invalid open is silence.
s/\$entry = \$bypage\[\$criteria->open\] \?\? null;/\$entry = \$bypage[\$criteria->open] ?? ['id' => \$criteria->open, 'name' => '', 'url' => '', 'expandable' => true];/;
