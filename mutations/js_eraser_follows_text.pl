# Keep the eraser hidden whatever the field holds: the client's one way to empty the search never shows.
s/eraser\.hidden = search\.value === '';/eraser.hidden = true;/;
