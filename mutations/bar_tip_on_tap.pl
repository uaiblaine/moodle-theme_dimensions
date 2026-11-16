# Leave a tapped control's tooltip on a touch screen.
s/\@media \(hover: none\) \{\n        \.dim-line \[data-dim-tip\]/\@media (hover: hover) and (pointer: fine) and (min-width: 99999px) {\n        .dim-line [data-dim-tip]/;
