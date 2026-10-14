# Take the public page text's editor back to maxfiles 0: core
# builds no file-picker options at all below that number, and tiny_media's embed dialogue and its
# media manager both dereference them unguarded, so the multimedia button throws
# "Cannot read properties of undefined (reading 'repositories')" and no dialogue opens. The image
# button beside it keeps working, which is what makes the defect read as a theme fault.
s/'maxfiles' => 1,/'maxfiles' => 0,/;
