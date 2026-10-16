# Keep the row under the line a box at rest: the toolbar's column then adds a gap under every bare page.
s/(\.dim-line__chips--rest \{\n        )display: contents;/${1}display: flex;/;
