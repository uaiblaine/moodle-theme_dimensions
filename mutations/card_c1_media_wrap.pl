# Rename the showmedia section to a flag nothing sets: the media band is drawn for every view setting.
s/\{\{#showmedia\}\}/{{^neverset}}/; s/\{\{\/showmedia\}\}/{{\/neverset}}/;
