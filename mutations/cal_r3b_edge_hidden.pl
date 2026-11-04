# Draw a disabled scroller arrow over the first day's header.
s/    \.dim-cal__trailedge\[aria-disabled="true"\] \{\n        visibility: hidden;\n    \}/    .dim-cal__trailedge[aria-disabled="true"] {\n        cursor: default;\n    }/;
