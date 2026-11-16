# Keep the line on one row whatever it holds: without the client a narrow page scrolls sideways.
s/(\.dim-line \{\n        --dim-bar-inner: 2rem;\n        position: relative;\n        display: flex;\n        )flex-wrap: wrap;/${1}flex-wrap: nowrap;/;
