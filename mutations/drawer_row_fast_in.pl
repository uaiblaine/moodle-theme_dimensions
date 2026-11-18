# Let a hovered drawer row arrive at the slow step: the hover reads late.
s/(&:hover \.dimensions-drawer-chevron \{\s*)transition-duration: var\(--dim-drawer-motion-fast\);/${1}transition-duration: var(--dim-drawer-motion-base);/;
