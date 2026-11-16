# Draw the bar's count from another number than the count sentence's.
s/(data-region="dim-line-total" aria-hidden="true">)\{\{countnumber\}\}/${1}{{filtercount}}/;
