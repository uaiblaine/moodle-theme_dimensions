# Hand the card no rating: the page reads the layer and draws nothing of it, on both shells.
s/\n                \$ratings\[\$courseid\] \?\? null,\n/\n                null,\n/;
