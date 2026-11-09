# Leave a step whose course has no image of its own without the renderer's cover.
s/cards::images\(\$refrows, false, true\)/cards::images(\$refrows, false, false)/;
