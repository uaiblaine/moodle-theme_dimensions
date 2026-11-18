# Let a hovered row arrive at the slow step: the hover reads late.
s/(\.dimensions-layer-row \{.*?&:hover \{.*?)transition-duration: var\(--dim-layer-motion-fast\);/${1}transition-duration: var(--dim-layer-motion-base);/s;
