# Paint the bar on a completed card after the seal: the card would show both.
s/(        markDone\(card\);\n)        return;\n/$1/;
