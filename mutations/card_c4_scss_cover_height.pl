# Keep the popover's old 64px band instead of the 96px cover.
s/(\.dim-cal__trailcover \{\n\s*flex: none;\n\s*)height: 6rem;/${1}height: 4rem;/;
