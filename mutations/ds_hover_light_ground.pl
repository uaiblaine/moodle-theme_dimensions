# Give the light page's hover the surface itself: a site whose tertiary ground equals its body ground hovers with nothing.
s/(\n    --dimensions-hover: )color-mix\(in srgb, var\(--dimensions-brand-tint\) 55%, var\(--dimensions-surface\)\);/${1}var(--dimensions-surface);/;
