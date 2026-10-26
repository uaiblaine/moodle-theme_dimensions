# Keep the first-use listener on after the preview is armed: every later pointer on the toolbar then
# gives its event a second time to the preview.
s/\n                form\.removeEventListener\(.pointerover., onPreviewIntent, true\);//;
