# Draw the page's own banner whatever the format, so a full width page shows two.
s/\{\{\^herofullwidth\}\}/{{^nosuchkey}}/;
s/\{\{\/herofullwidth\}\}/{{\/nosuchkey}}/;
