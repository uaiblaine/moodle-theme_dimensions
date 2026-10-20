# Drop the primary drawer's reset of core's anchored right offset: at tablet width it opens 58px wide again, cut at the left edge.
s/(#theme_boost-drawers-primary\.drawer-primary\.drawer-left \{\n\s*left: [^\n]*\n\s*)right: auto;/$1/;
