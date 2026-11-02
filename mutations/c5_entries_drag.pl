# Let every editable event be dragged, an override included.
s/'drag' => \(\$limits\['draggable'\] \?\? true\) !== false,/'drag' => true,/;
